<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class ScheduleImpactService
{
    public function blockBookings(array $details, bool $lock = false)
    {
        return Reservation::where('facility_id', $details['facility_id'])->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
            ->whereDate('reservation_date', '>=', $details['starts_on'])->whereDate('reservation_date', '<=', $details['ends_on'])
            ->when($details['start_time'] ?? null, fn ($q) => $q->where('start_time', '<', $details['end_time'])->where('end_time', '>', $details['start_time']))
            ->when($lock, fn ($q) => $q->lockForUpdate())->orderBy('id')->get();
    }

    public function token(string $action, array $details, $records): string
    {
        return Crypt::encryptString(json_encode(['fingerprint' => $this->fingerprint($action, $details, $records),
            'admin' => auth()->id(), 'expires' => now()->addMinutes(10)->timestamp], JSON_THROW_ON_ERROR));
    }

    public function confirm(?string $token, string $action, array $details, $records): void
    {
        try {
            $preview = json_decode(Crypt::decryptString($token ?? ''), true, flags: JSON_THROW_ON_ERROR);
            if ($preview['admin'] === auth()->id() && $preview['expires'] > now()->timestamp
                && hash_equals($preview['fingerprint'], $this->fingerprint($action, $details, $records))) return;
        } catch (\Throwable $exception) {
            // Missing, expired or modified previews require a fresh review.
        }
        throw ValidationException::withMessages(['impact_token' => 'Review the affected bookings again before confirming. The preview is missing, expired, or the schedule has changed.']);
    }

    private function fingerprint(string $action, array $details, $records): string
    {
        unset($details['impact_token']);
        ksort($details);
        return hash('sha256', json_encode([$action, $details, $records->sortBy('id')->map(fn ($r) =>
            $r->only(['id', 'facility_id', 'reservation_date', 'start_time', 'end_time', 'status', 'contact_email', 'updated_at']))->values()->all()], JSON_THROW_ON_ERROR));
    }

    public function row(Reservation $booking, string $outcome): array
    {
        return ['reference' => $booking->reference_number, 'date' => $booking->reservation_date->toDateString(),
            'time' => substr($booking->start_time, 0, 5).' – '.(str_starts_with($booking->end_time, '24:00') ? '12:00 midnight' : substr($booking->end_time, 0, 5)),
            'status' => ucwords(str_replace('_', ' ', $booking->status)), 'outcome' => $outcome,
            'email' => $booking->contact_email ?: 'No email on record; contact requestor manually.',
            'url' => route('admin.reservations.show', $booking).'#conflict-resolution'];
    }

    public function alternatives(Reservation $selected, array $target): array
    {
        $calendar = app(PublicCalendarService::class);
        $busy = Reservation::whereKeyNot($selected->id)->where('facility_id', $target['facility_id'])
            ->whereDate('reservation_date', $target['reservation_date'])->whereIn('status', PublicCalendarService::BLOCKING_STATUSES)
            ->get()->map(fn ($r) => ['start_time' => $calendar->bufferedTime($r->start_time, -PublicCalendarService::CLEANING_MINUTES),
                'end_time' => $calendar->bufferedTime($r->end_time, PublicCalendarService::CLEANING_MINUTES)])->all();
        foreach (\App\Models\ScheduleBlock::whereDate('starts_on', '<=', $target['reservation_date'])
            ->whereDate('ends_on', '>=', $target['reservation_date'])
            ->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $target['facility_id']))->get() as $block) {
            $busy[] = ['start_time' => $block->start_time ?? '00:00', 'end_time' => $block->end_time ?? '24:00'];
        }
        $minutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        $duration = $minutes($target['end_time']) - $minutes($target['start_time']);
        $choices = [];
        foreach ($calendar->slots($busy, config('gym.reservation.opening_time', '07:00'), config('gym.reservation.closing_time', '24:00')) as $slot) {
            if (! $slot['available'] || $minutes($slot['end_time']) - $minutes($slot['start_time']) < $duration) continue;
            $end = $calendar->bufferedTime($slot['start_time'], $duration);
            $choices[] = ['date' => $target['reservation_date'], 'start' => $slot['start_time'], 'end' => $end,
                'label' => $target['reservation_date'].' '.$slot['start_time'].'–'.($end === '24:00' ? '12:00 midnight' : $end)];
        }
        return array_slice($choices, 0, 3);
    }
}
