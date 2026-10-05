<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use App\Models\SystemSetting;
use Carbon\CarbonImmutable;

class PublicCalendarService
{
    public function month(Facility $facility, string $month): array
    {
        $first = CarbonImmutable::createFromFormat('!Y-m', $month);
        $last = $first->endOfMonth();
        $bookings = Reservation::where('facility_id', $facility->id)
            ->whereBetween('reservation_date', [$first->toDateString(), $last->toDateString()])
            ->whereNotIn('status', ['rejected', 'cancelled'])->get(['reservation_date', 'start_time', 'end_time'])
            ->groupBy(fn ($row) => $row->reservation_date->toDateString());
        $blocks = ScheduleBlock::whereDate('starts_on', '<=', $last)->whereDate('ends_on', '>=', $first)
            ->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $facility->id))->get();
        $opening = config('gym.reservation.opening_time', '08:00');
        $closing = config('gym.reservation.closing_time', '21:00');
        $minimum = now()->addDays(max(0, (int) SystemSetting::getValue('minimum_notice_days', config('gym.reservation.minimum_notice_days', 3))))->toDateString();
        $maximum = now()->addDays((int) SystemSetting::getValue('maximum_advance_days', 365))->toDateString();
        $days = [];
        for ($day = $first; $day <= $last; $day = $day->addDay()) {
            $date = $day->toDateString();
            $busy = [];
            foreach ($bookings->get($date, collect()) as $booking) {
                $busy[] = ['start_time' => $booking->start_time, 'end_time' => $booking->end_time, 'status' => 'reserved'];
            }
            foreach ($blocks as $block) {
                if ($block->starts_on->toDateString() <= $date && $block->ends_on->toDateString() >= $date) {
                    $busy[] = ['start_time' => $block->start_time ?? $opening, 'end_time' => $block->end_time ?? $closing, 'status' => 'blackout'];
                }
            }
            if ($facility->status !== 'available') {
                $busy[] = ['start_time' => $opening, 'end_time' => $closing, 'status' => 'blackout'];
            }
            $slots = $this->slots($busy, $opening, $closing);
            $available = count(array_filter($slots, fn ($slot) => $slot['available']));
            $status = $available === 0 ? 'full' : ($available < count($slots) ? 'partial' : 'available');
            $days[$date] = ['date' => $date, 'status' => $status, 'label' => match ($status) {
                'full' => 'Fully Booked', 'partial' => 'Available Time', default => 'Available',
            }, 'selectable' => $date >= $minimum && $date <= $maximum, 'slots' => $slots, 'occupied' => $busy];
        }
        return ['days' => $days, 'opening_time' => $opening, 'closing_time' => $closing];
    }

    public function slots(array $busy, string $opening, string $closing): array
    {
        $minutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        $format = fn ($value) => sprintf('%02d:%02d', intdiv($value, 60), $value % 60);
        $start = $minutes($opening); $end = $minutes($closing);
        $boundaries = [$start, $end];
        foreach ($busy as $interval) {
            $left = max($start, $minutes($interval['start_time']));
            $right = min($end, $minutes($interval['end_time']));
            if ($left < $right) { $boundaries[] = $left; $boundaries[] = $right; }
        }
        $boundaries = array_values(array_unique($boundaries)); sort($boundaries);
        $segments = [];
        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            $left = $boundaries[$i]; $right = $boundaries[$i + 1];
            $reserved = false;
            foreach ($busy as $interval) {
                if ($minutes($interval['start_time']) < $right && $minutes($interval['end_time']) > $left) { $reserved = true; break; }
            }
            $previous = count($segments) - 1;
            if ($previous >= 0 && $segments[$previous]['available'] === !$reserved) {
                $segments[$previous]['end'] = $right;
            } else { $segments[] = ['start' => $left, 'end' => $right, 'available' => !$reserved]; }
        }
        // Return entire contiguous periods; requestors choose their own duration.
        return array_map(fn ($segment) => [
            'start_time' => $format($segment['start']), 'end_time' => $format($segment['end']),
            'available' => $segment['available'], 'label' => $segment['available'] ? 'Available' : 'Reserved',
        ], $segments);
    }
}
