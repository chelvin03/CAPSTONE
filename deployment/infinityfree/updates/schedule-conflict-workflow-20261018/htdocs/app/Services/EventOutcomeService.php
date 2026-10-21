<?php

namespace App\Services;

use App\Models\{AuditLog, Facility, Reservation, ReservationEventOutcome, ReservationStatusHistory, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventOutcomeService
{
    public function endAt(Reservation $reservation): CarbonImmutable
    {
        $minutes = (int) substr($reservation->end_time, 0, 2) * 60 + (int) substr($reservation->end_time, 3, 2);
        // Older bookings may represent the closing midnight as 00:00.
        if ($minutes === 0 && substr($reservation->start_time, 0, 5) > '00:00') $minutes = 1440;
        return CarbonImmutable::parse($reservation->reservation_date)->startOfDay()->addMinutes($minutes);
    }

    public function schedule(Reservation $reservation): array
    {
        return ['date' => $reservation->reservation_date->toDateString(), 'start' => substr($reservation->start_time, 0, 5),
            'end' => substr($reservation->end_time, 0, 5), 'facility_id' => $reservation->facility_id];
    }

    public function record(Reservation $booking, User $actor, array $data): void
    {
        abort_unless(in_array($actor->role, ['admin', 'staff'], true) && $actor->status === 'approved', 403);
        DB::transaction(function () use ($booking, $actor, $data): void {
            app(EquipmentRequestService::class)->lockInventory();
            Facility::whereKey($booking->facility_id)->lockForUpdate()->firstOrFail();
            $reservation = Reservation::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $existing = $reservation->eventOutcome()->lockForUpdate()->first();
            if (! in_array($reservation->status, ['approved', 'completed', 'no_show'], true) || $this->endAt($reservation)->isFuture()) {
                throw ValidationException::withMessages(['outcome' => 'Record an outcome only after an approved event has ended. Pending, rejected and cancelled requests cannot be marked completed or no-show.']);
            }
            if ((int) ($data['revision'] ?? 0) !== ($existing?->revision ?? 0)) {
                throw ValidationException::withMessages(['outcome' => 'This outcome changed while you were reviewing it. Reload the reservation before saving.']);
            }
            if ($existing) {
                abort_unless($actor->role === 'admin', 403);
                if (blank($data['correction_reason'] ?? null)) {
                    throw ValidationException::withMessages(['correction_reason' => 'Explain why this recorded outcome needs correction.']);
                }
                if ($existing->schedule !== $this->schedule($reservation)) {
                    throw ValidationException::withMessages(['outcome' => 'The schedule differs from the recorded event. Review the reservation history before correcting it.']);
                }
            }
            $actual = $data['outcome'] === 'no_show' ? 0 : (int) $data['actual_attendees'];
            if ($data['outcome'] === 'no_show' && (int) ($data['actual_attendees'] ?? 0) !== 0) {
                throw ValidationException::withMessages(['actual_attendees' => 'A no-show must have zero attendees. Choose Completed for an event that took place.']);
            }
            if ($data['outcome'] === 'completed' && $actual < 1) {
                throw ValidationException::withMessages(['actual_attendees' => 'Enter at least one actual attendee for a completed event.']);
            }
            $before = $existing?->toArray();
            $previous = $reservation->status;
            $reservation->update(['status' => $data['outcome'], 'completed_at' => $data['outcome'] === 'completed' ? ($reservation->completed_at ?? now()) : null]);
            $reservation->eventOutcome()->updateOrCreate([], ['outcome' => $data['outcome'], 'actual_attendees' => $actual,
                'expected_attendees' => $existing?->expected_attendees ?? $reservation->expected_attendees, 'schedule' => $this->schedule($reservation),
                'remarks' => $data['remarks'], 'recorded_by' => $actor->id, 'recorded_at' => now(), 'revision' => ($existing?->revision ?? 0) + 1]);
            ReservationStatusHistory::create(['reservation_id' => $reservation->id, 'changed_by' => $actor->id,
                'previous_status' => $previous, 'new_status' => $data['outcome'],
                'remarks' => ($existing ? 'Event outcome corrected: '.$data['correction_reason'].'. ' : 'Event outcome recorded: ').$data['remarks']]);
            AuditLog::record($existing ? 'reservation.event_outcome_corrected' : 'reservation.event_outcome_recorded', $reservation,
                ['previous_outcome' => $before, 'outcome' => $data['outcome'], 'actual_attendees' => $actual,
                    'remarks' => $data['remarks'], 'correction_reason' => $data['correction_reason'] ?? null]);
        });
    }
}
