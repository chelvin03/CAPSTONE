<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Facility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AdminScheduleService
{
    public function month(CarbonImmutable $month, Collection $facilities, Collection $reservations, Collection $blocks): array
    {
        $hours = $this->hours();
        $days = [];
        $grouped = $reservations->groupBy(fn ($row) => $row->reservation_date->toDateString());
        for ($date = $month; $date->month === $month->month; $date = $date->addDay()) {
            $dayBookings = $grouped->get($date->toDateString(), collect());
            $dayBlocks = $blocks->filter(fn ($block) => $block->starts_on->toDateString() <= $date->toDateString()
                && $block->ends_on->toDateString() >= $date->toDateString());
            $panels = $facilities->map(fn ($facility) => $this->facilityDay($date, $facility, $dayBookings, $dayBlocks, $hours))->values()->all();
            $segments = collect($panels)->flatMap(fn ($panel) => $panel['intervals']);
            $available = $segments->where('state', 'available')->sum('seconds');
            $approved = $dayBookings->whereIn('status', ['approved', 'completed'])->count();
            $pending = $dayBookings->whereNotIn('status', ['approved', 'completed', 'rejected', 'cancelled', 'no_show'])->count();
            $closed = $segments->where('state', 'closed')->sum('seconds');
            $mixed = $segments->where('withinHours', true)->pluck('state')->unique()->count() > 1;
            $pastDate = $date->lt(CarbonImmutable::today());
            $state = $pastDate ? 'closed' : (! $hours ? 'unknown' : ($mixed ? 'partial' : ($available > 0
                ? 'available'
                : ($segments->contains('state', 'occupied') ? 'occupied' : ($segments->contains('state', 'pending') ? 'pending' : 'closed')))));
            $held = $segments->where('withinHours', true)->whereIn('state', ['occupied', 'pending'])->sum('seconds');
            $calendarState = $pastDate || ! $hours ? 'closed' : ($available > 0 ? ($held > 0 || $closed > 0 && $mixed ? 'partial' : 'available') : ($held > 0 ? 'full' : 'closed'));
            $calendarLabel = $calendarState === 'full' ? 'Fully Booked'
                : match ($calendarState) { 'partial' => 'Partial - Available Time', 'available' => 'Available', default => 'Closed / Unavailable' };
            $days[$date->toDateString()] = [
                'issues' => $this->issues($dayBookings, $dayBlocks),
                'calendarState' => $calendarState, 'calendarLabel' => $calendarLabel,
                'date' => $date->toDateString(), 'number' => $date->day, 'label' => $date->format('l, F j, Y'),
                'today' => $date->isToday(), 'state' => $state,
                'stateLabel' => match ($state) {
                    'partial' => 'Partially occupied / restricted', 'occupied' => 'Occupied', 'pending' => 'Pending requests', 'unknown' => 'Availability unconfirmed', 'closed' => 'Closed / non-bookable', default => 'Available'
                },
                'approvedCount' => $approved, 'pendingCount' => $pending, 'hasAvailable' => $available > 0,
                'hasClosed' => $closed > 0 || ! $hours, 'conflictCount' => $segments->where('conflict', true)->count(),
                'facilities' => $panels,
            ];
        }

        return ['days' => $days, 'hours' => $hours];
    }

    private function hours(): ?array
    {
        $open = config('gym.reservation.opening_time');
        $close = config('gym.reservation.closing_time');
        if (! is_string($open) || ! is_string($close)
            || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $open)
            || ($close !== '24:00' && ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $close)) || $close <= $open) {
            return null;
        }

        return ['start' => $this->seconds($open), 'end' => $this->seconds($close), 'label' => $this->label($this->seconds($open)).' – '.$this->label($this->seconds($close))];
    }

    private function issues(Collection $reservations, Collection $blocks): array
    {
        $active = $reservations->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])->values();
        $issues = [];
        $booking = fn ($r) => ['reference' => $r->reference_number, 'status' => ucwords(str_replace('_', ' ', $r->status)),
            'time' => $this->label($this->seconds($r->start_time)).' – '.$this->label($this->seconds($r->end_time)),
            'url' => route('admin.reservations.show', $r->id).'#conflict-resolution'];
        foreach ($active as $i => $left) {
            foreach ($active->slice($i + 1) as $right) {
                if ((int) $left->facility_id !== (int) $right->facility_id) continue;
                $confirmed = in_array($left->status, PublicCalendarService::BLOCKING_STATUSES, true)
                    || in_array($right->status, PublicCalendarService::BLOCKING_STATUSES, true);
                $buffer = PublicCalendarService::CLEANING_MINUTES * 60;
                if ($this->seconds($left->start_time) >= $this->seconds($right->end_time) + $buffer
                    || $this->seconds($right->start_time) >= $this->seconds($left->end_time) + $buffer) continue;
                $both = in_array($left->status, PublicCalendarService::BLOCKING_STATUSES, true)
                    && in_array($right->status, PublicCalendarService::BLOCKING_STATUSES, true);
                $issues[] = ['title' => $both ? 'Confirmed booking / cleaning conflict' : ($confirmed ? 'Request conflicts with confirmed time' : 'Unconfirmed requests need schedule review'),
                    'description' => $both ? 'Confirmed schedules overlap or lack the required cleaning separation. Reschedule one booking or review an authorized force assignment.'
                        : ($confirmed ? 'The request cannot be approved at this time while the confirmed booking and cleaning gap remain. Choose a new schedule or resolve the confirmed booking first.'
                            : 'These requests overlap or leave too little cleaning time, but are not a confirmed double booking. Only one conflicting request may be approved; reschedule, waitlist or cancel others as appropriate.'),
                    'bookings' => [$booking($left), $booking($right)], 'blocked' => false];
            }
            foreach ($blocks as $block) {
                if ($block->facility_id !== null && (int) $block->facility_id !== (int) $left->facility_id) continue;
                if (($block->start_time ? $this->seconds($block->start_time) : 0) >= $this->seconds($left->end_time)
                    || ($block->end_time ? $this->seconds($block->end_time) : 86400) <= $this->seconds($left->start_time)) continue;
                $issues[] = ['title' => 'Booking/request overlaps an administrator block',
                    'description' => 'Block: '.$block->title.'. Move the booking/request, or review and release the block before approval. Completed records must remain unchanged.',
                    'bookings' => [$booking($left)], 'blocked' => true];
            }
        }
        return $issues;
    }

    private function facilityDay(CarbonImmutable $date, Facility $facility, Collection $reservations, Collection $blocks, ?array $hours): array
    {
        // Match existing backend overlap semantics: setup/cleanup fields do not extend blocked times.
        $bookings = $reservations->where('facility_id', $facility->id)->whereNotIn('status', ['rejected', 'cancelled', 'no_show'])
            ->filter(fn ($row) => $row->end_time > $row->start_time)->values();
        $locks = $blocks->filter(fn ($block) => $block->facility_id === null || (int) $block->facility_id === (int) $facility->id)->values();
        $points = [0, 86400];
        if ($hours) {
            $points = [...$points, $hours['start'], $hours['end']];
        }
        foreach ($bookings as $booking) {
            $points = [...$points, $this->seconds($booking->start_time), $this->seconds($booking->end_time)];
        }
        foreach ($locks as $lock) {
            $points = [...$points, $lock->start_time ? $this->seconds($lock->start_time) : 0, $lock->end_time ? $this->seconds($lock->end_time) : 86400];
        }
        if ($date->isToday()) {
            $points[] = $this->seconds(now()->format('H:i:s'));
        }
        $points = array_values(array_unique($points));
        sort($points);
        $intervals = [];
        for ($index = 0; $index < count($points) - 1; $index++) {
            [$start, $end] = [$points[$index], $points[$index + 1]];
            if ($end <= $start) {
                continue;
            }
            $active = $bookings->filter(fn ($row) => $this->seconds($row->start_time) < $end && $this->seconds($row->end_time) > $start);
            $approved = $active->whereIn('status', ['approved', 'completed'])->count();
            $pending = $active->count() - $approved;
            $reasons = [];
            $withinHours = $hours && $start >= $hours['start'] && $end <= $hours['end'];
            if ($facility->status !== 'available') {
                $reasons[] = 'Facility not reservable';
            }
            if ($hours && ! $withinHours) {
                $reasons[] = 'Outside operating hours';
            }
            if ($date->lt(CarbonImmutable::today()) || ($date->isToday() && $end <= $this->seconds(now()->format('H:i:s')))) {
                $reasons[] = 'Past time';
            }
            if ($locks->contains(fn ($lock) => ($lock->start_time ? $this->seconds($lock->start_time) : 0) < $end
                && ($lock->end_time ? $this->seconds($lock->end_time) : 86400) > $start)) {
                $reasons[] = 'Administrator schedule block';
            }
            $state = $reasons ? 'closed' : ($approved ? 'occupied' : ($hours ? 'available' : 'unknown'));
            $rows = $active->map(fn ($row) => ['reference' => $row->reference_number, 'status' => ucwords(str_replace('_', ' ', $row->status)), 'url' => route('admin.reservations.show', $row->id)])->values()->all();
            $segment = ['start' => $start, 'end' => $end, 'state' => $state, 'withinHours' => (bool) $withinHours,
                'label' => match ($state) {
                    'occupied' => 'Approved reservation', 'pending' => 'Pending / awaiting decision', 'closed' => 'Closed / non-bookable', 'unknown' => 'Availability unconfirmed', default => 'Available'
                },
                'approvedCount' => $approved, 'pendingCount' => $pending, 'conflict' => $active->count() > 1 || ($active->isNotEmpty() && in_array('Administrator schedule block', $reasons, true)),
                'reasons' => $reasons, 'bookings' => $rows];
            $last = count($intervals) - 1;
            if ($last >= 0 && $intervals[$last]['state'] === $state && $intervals[$last]['reasons'] === $reasons
                && $intervals[$last]['bookings'] === $rows && $intervals[$last]['withinHours'] === (bool) $withinHours) {
                $intervals[$last]['end'] = $end;
            } else {
                $intervals[] = $segment;
            }
        }
        foreach ($intervals as &$interval) {
            $interval['time'] = $this->label($interval['start']).' – '.$this->label($interval['end']);
            $interval['seconds'] = $interval['end'] - $interval['start'];
        }
        unset($interval);

        return ['id' => $facility->id, 'name' => $facility->facility_name, 'intervals' => $intervals];
    }

    private function seconds(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));

        return $parts[0] * 3600 + $parts[1] * 60 + ($parts[2] ?? 0);
    }

    private function label(int $seconds): string
    {
        if ($seconds === 86400) {
            return '12:00 AM (next day)';
        }
        $hour = intdiv($seconds, 3600);
        $minute = intdiv($seconds % 3600, 60);
        $remainder = $seconds % 60;

        return ($hour % 12 ?: 12).':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT)
            .($remainder ? ':'.str_pad((string) $remainder, 2, '0', STR_PAD_LEFT) : '').($hour < 12 ? ' AM' : ' PM');
    }
}
