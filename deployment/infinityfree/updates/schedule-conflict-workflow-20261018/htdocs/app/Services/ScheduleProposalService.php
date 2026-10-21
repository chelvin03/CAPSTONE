<?php

namespace App\Services;

use App\Models\{AuditLog, Facility, Reservation, ReservationStatusHistory, ScheduleBlock, ScheduleProposal};
use Illuminate\Support\Facades\{DB, Log, Mail, URL};
use Illuminate\Validation\ValidationException;

class ScheduleProposalService
{
    public function snapshot(Reservation $reservation): array
    {
        return ['facility_id' => $reservation->facility_id, 'reservation_date' => $reservation->reservation_date->toDateString(),
            'start_time' => substr($reservation->start_time, 0, 5), 'end_time' => substr($reservation->end_time, 0, 5),
            'status' => $reservation->status, 'contact_email' => $reservation->contact_email];
    }

    public function assertAvailable(Reservation $reservation, array $target): void
    {
        if (\App\Models\EquipmentReturn::where('reservation_id', $reservation->id)->exists()) {
            throw ValidationException::withMessages(['schedule' => 'This event already has equipment return records. Record or correct its event outcome instead of moving its historical schedule.']);
        }
        $minutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        $starts = \Carbon\Carbon::parse($target['reservation_date'])->startOfDay()->addMinutes($minutes($target['start_time']));
        if (! $starts->isFuture() || $minutes($target['end_time']) - $minutes($target['start_time']) < 60
            || $target['start_time'] < config('gym.reservation.opening_time', '07:00')
            || $target['end_time'] > config('gym.reservation.closing_time', '24:00')
            || Facility::findOrFail($target['facility_id'])->status !== 'available') {
            throw ValidationException::withMessages(['schedule' => 'Choose an available future schedule of at least one hour within operating hours.']);
        }
        $calendar = app(PublicCalendarService::class);
        if (Reservation::whereKeyNot($reservation->id)->where('facility_id', $target['facility_id'])
            ->whereDate('reservation_date', $target['reservation_date'])->whereIn('status', PublicCalendarService::BLOCKING_STATUSES)
            ->where('start_time', '<', $calendar->bufferedTime($target['end_time'], PublicCalendarService::CLEANING_MINUTES))
            ->where('end_time', '>', $calendar->bufferedTime($target['start_time'], -PublicCalendarService::CLEANING_MINUTES))->exists()
            || ScheduleBlock::whereDate('starts_on', '<=', $target['reservation_date'])->whereDate('ends_on', '>=', $target['reservation_date'])
                ->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $target['facility_id']))
                ->where(fn ($q) => $q->whereNull('start_time')->orWhere(fn ($t) => $t->where('start_time', '<', $target['end_time'])->where('end_time', '>', $target['start_time'])))->exists()) {
            throw ValidationException::withMessages(['schedule' => 'The proposed slot is no longer available, including its cleaning gap. Your original booking has not changed. Contact the administrator for another proposal.']);
        }
        $candidate = clone $reservation;
        $candidate->fill($target);
        app(EquipmentRequestService::class)->assertAllocations($candidate);
    }

    // Called while the facility, inventory and reservation locks are held.
    public function propose(Reservation $reservation, array $details, int $adminId): ScheduleProposal
    {
        if (! filter_var($reservation->contact_email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['schedule' => 'A valid requestor email is required to send a proposal.']);
        }
        $target = array_intersect_key($details, array_flip(['facility_id', 'reservation_date', 'start_time', 'end_time']));
        $this->assertAvailable($reservation, $target);
        $original = $this->snapshot($reservation);
        $kind = ($details['proposal_kind'] ?? '') === 'waiting_list' ? 'waiting_list' : 'replacement';
        if ($kind === 'waiting_list') {
            if ($reservation->status !== 'waiting_list') throw ValidationException::withMessages(['schedule' => 'Only waiting-list requests may receive an approval offer.']);
            \App\Models\RequestorRestriction::assertCanReserve($reservation->contact_email);
        }
        if ($kind !== 'waiting_list' && $target === array_intersect_key($original, $target)) {
            throw ValidationException::withMessages(['schedule' => 'Choose a replacement schedule different from the original.']);
        }
        ScheduleProposal::where('reservation_id', $reservation->id)->where('status', 'pending')->update(['status' => 'superseded']);
        $start = \Carbon\Carbon::parse($target['reservation_date'].' '.$target['start_time']);
        $proposal = ScheduleProposal::create(['reservation_id' => $reservation->id, 'created_by' => $adminId,
            'original' => $original, 'target' => $target, 'recipient' => $reservation->contact_email, 'kind' => $kind,
            'reason' => $details['reason'], 'expires_at' => now()->addHours($kind === 'waiting_list' ? 24 : 48)->min($start)]);
        AuditLog::record('reservation.schedule_proposed', $reservation, ['proposal_id' => $proposal->id, 'target' => $target, 'reason' => $details['reason']]);
        DB::afterCommit(function () use ($proposal): void {
            try {
                $link = URL::temporarySignedRoute('reservation.schedule-proposal', $proposal->expires_at, ['proposal' => $proposal->id]);
                Mail::to($proposal->recipient)->send(new \App\Mail\ReplacementScheduleProposed($proposal, $link));
                $proposal->update(['mail_status' => 'sent']);
            } catch (\Throwable $exception) {
                $proposal->update(['mail_status' => 'failed']);
                Log::error('Schedule proposal email failed.', ['proposal_id' => $proposal->id, 'exception_type' => get_class($exception)]);
            }
        });
        return $proposal;
    }

    public function respond(ScheduleProposal $proposal, string $decision): void
    {
        DB::transaction(function () use ($proposal, $decision): void {
            app(EquipmentRequestService::class)->lockInventory();
            Facility::whereKey($proposal->target['facility_id'])->lockForUpdate()->firstOrFail();
            $reservation = Reservation::whereKey($proposal->reservation_id)->lockForUpdate()->firstOrFail();
            $proposal = ScheduleProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ($proposal->status !== 'pending' || $proposal->expires_at->lte(now())) {
                throw ValidationException::withMessages(['schedule' => 'This proposal has expired or already received a response. Contact the administrator if needed.']);
            }
            if ($this->snapshot($reservation) !== $proposal->original || in_array($reservation->status, ['cancelled', 'rejected', 'completed', 'no_show'], true)) {
                throw ValidationException::withMessages(['schedule' => 'Your reservation changed after this proposal was sent. This offer can no longer be applied. Contact the administrator.']);
            }
            if ($decision === 'accepted') {
                $this->assertAvailable($reservation, $proposal->target);
                $previous = $reservation->status;
                $changes = $proposal->target;
                if ($proposal->kind === 'waiting_list') {
                    if ($reservation->status !== 'waiting_list') throw ValidationException::withMessages(['schedule' => 'This request is no longer waiting for a slot.']);
                    \App\Models\RequestorRestriction::assertCanReserve($reservation->contact_email);
                    $changes += ['status' => 'approved', 'approved_by' => $proposal->created_by, 'approved_at' => now()];
                }
                $reservation->update($changes);
                ReservationStatusHistory::create(['reservation_id' => $reservation->id, 'changed_by' => $proposal->created_by,
                    'previous_status' => $previous, 'new_status' => $reservation->status,
                    'remarks' => 'Requestor accepted replacement schedule #'.$proposal->id.': '.$proposal->reason]);
                DB::afterCommit(function () use ($proposal, $reservation): void {
                    try {
                        Mail::to($proposal->recipient)->send(new \App\Mail\ReservationScheduleChanged($reservation, $proposal->reason, $proposal->original, $proposal->kind === 'waiting_list' ? 'waiting_list_accepted' : 'accepted'));
                    } catch (\Throwable $exception) {
                        Log::error('Accepted schedule confirmation email failed.', ['proposal_id' => $proposal->id, 'exception_type' => get_class($exception)]);
                    }
                });
            }
            $proposal->update(['status' => $decision, 'responded_at' => now()]);
            AuditLog::record('reservation.schedule_proposal_'.$decision, $reservation, ['proposal_id' => $proposal->id, 'actor' => 'requestor via signed email link']);
        });
    }
}
