<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class DashboardInsightService
{
    public function summarize(array $filters, DashboardAnalyticsService $analytics): array
    {
        $records = $analytics->query($filters)->withCount(['documents as permit_count' => fn ($q) => $q->where('document_type', 'permit')->whereNotNull('file_path')->where('file_path', '<>', '')])->get();
        $issues = [];
        $incomplete = 0;
        foreach ($records as $row) {
            // Public submissions have no user_id; administrator entries do not require a permit/email.
            $public = $row->user_id === null;
            $checks = [
                'Missing event name' => blank($row->event_name),
                'Missing purpose' => blank($row->purpose),
                'Missing requestor type or facility' => blank($row->reservation_type) || ! $row->facility_id,
                'Missing contact information' => blank($row->contact_person) || blank($row->contact_number) || ($public && blank($row->contact_email)),
                'Missing expected attendees' => $row->getRawOriginal('expected_attendees') === null,
                'Invalid attendee count' => $row->getRawOriginal('expected_attendees') !== null && (! ctype_digit((string) $row->getRawOriginal('expected_attendees')) || $row->expected_attendees < \App\Support\GymCapacity::MIN_ATTENDEES),
                'Attendees exceed gym capacity (2,000)' => $row->expected_attendees > 2000,
                'Missing or invalid reservation date/time' => ! $row->reservation_date || blank($row->start_time) || blank($row->end_time) || $row->end_time <= $row->start_time,
                'Missing required approval letter' => $public && ! $row->permit_count,
            ];
            $incomplete += in_array(true, $checks, true) ? 1 : 0;
            // Event type is optional in both current workflows: flag for review, not completeness.
            $checks['Event type not recorded (optional)'] = blank($row->event_type);
            foreach ($checks as $description => $affected) {
                if ($affected) $issues[$description][] = ['id' => $row->id, 'reference' => $row->reference_number];
            }
        }

        $monthly = collect(range(1, 12))->map(fn ($month) => ['label' => CarbonImmutable::create(2000, $month, 1)->format('M'), 'count' => 0, 'cancelled' => 0]);
        foreach ($records->filter(fn ($row) => $row->reservation_date)->groupBy(fn ($row) => $row->reservation_date->month) as $month => $rows) {
            $monthly[$month - 1] = ['label' => $monthly[$month - 1]['label'], 'count' => $rows->count(), 'cancelled' => $rows->where('status', 'cancelled')->count()];
        }
        $monthly = $monthly->map(fn ($row) => $row + ['rate' => $row['count'] ? round($row['cancelled'] / $row['count'] * 100, 1) : 0]);
        $total = $records->count();
        $peaks = $monthly->where('count', '>', 0)->sortByDesc('count')->take(3)->values()->map(fn ($row) => $row + ['percentage' => round($row['count'] / $total * 100, 1)]);

        $heat = [];
        $validTime = fn ($value) => is_string($value) && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$|^24:00$/', $value);
        $opening = config('gym.reservation.opening_time');
        $closing = config('gym.reservation.closing_time');
        $heatUsesOperatingHours = $validTime($opening) && $validTime($closing) && $closing > $opening;
        $open = $this->minutes($heatUsesOperatingHours ? $opening : '06:00');
        $close = $this->minutes($heatUsesOperatingHours ? $closing : '22:00');
        for ($start = $open; $start < $close; $start += 120) {
            $end = min($close, $start + 120);
            $heat[] = ['label' => $this->clock($start).'–'.$this->clock($end), 'start' => $start, 'end' => $end, 'counts' => array_fill(0, 7, 0), 'month_counts' => array_fill(0, 12, 0)];
        }
        $days = array_fill(0, 7, 0);
        $heatMonths = array_fill(1, 12, 0);
        foreach ($records as $row) {
            if (! $row->reservation_date || (! isset($filters['status']) && ($filters['heat_scope'] ?? 'approved') !== 'all' && $row->status !== 'approved')) continue;
            $day = $row->reservation_date->dayOfWeekIso - 1;
            $days[$day]++;
            $heatMonths[$row->reservation_date->month]++;
            foreach ($heat as &$slot) {
                if ($this->minutes($row->start_time ?? '00:00') < $slot['end'] && $this->minutes($row->end_time ?? '00:00') > $slot['start'] && $row->end_time > $row->start_time) {
                    $slot['counts'][$day]++;
                    $slot['month_counts'][$row->reservation_date->month - 1]++;
                }
            }
            unset($slot);
        }
        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $heatMax = max(1, ...array_merge(...array_column($heat, 'counts')));
        $monthHeatMax = max(1, ...array_merge(...array_column($heat, 'month_counts')));
        $slotTotals = array_map(fn ($slot) => array_sum($slot['counts']), $heat);
        $peakTime = $slotTotals && max($slotTotals) ? $heat[array_search(max($slotTotals), $slotTotals)]['label'] : 'No activity';

        // A past selected period is an as-of analysis; future filters cannot turn future bookings into history.
        $cutoff = CarbonImmutable::parse($filters['date_to'])->endOfDay()->min(CarbonImmutable::now());
        $boundary = $cutoff->startOfMonth();
        if ($cutoff->isLastOfMonth() && $cutoff->lt(CarbonImmutable::now()->startOfMonth())) $boundary = $boundary->addMonth();
        $historyQuery = $analytics->query(Arr::except($filters, ['year', 'month', 'day', 'all_time']), false)->where('reservation_date', '<', $boundary->toDateString());
        $first = (clone $historyQuery)->min('reservation_date');
        $historyRows = (clone $historyQuery)->where('reservation_date', '>=', $boundary->subMonths(3)->toDateString())->get(['reservation_date']);
        $history = [];
        for ($i = 3; $i >= 1; $i--) {
            $month = $boundary->subMonths($i);
            $history[] = ['label' => $month->format('M Y'), 'count' => $historyRows->filter(fn ($r) => $r->reservation_date->format('Y-m') === $month->format('Y-m'))->count()];
        }
        $sufficient = $first && CarbonImmutable::parse($first)->startOfMonth()->lte($boundary->subMonths(3));
        $estimate = $sufficient ? round(array_sum(array_column($history, 'count')) / 3, 1) : null;
        $forecast = [];
        $horizon = $cutoff->startOfMonth()->addMonth();
        for ($i = 0; $i < 3; $i++) $forecast[] = ['label' => $horizon->addMonths($i)->format('M Y'), 'count' => $estimate ?? 0];

        return compact('issues', 'monthly', 'peaks', 'heat', 'heatMax', 'monthHeatMax', 'heatUsesOperatingHours', 'weekdays', 'peakTime', 'history', 'forecast', 'estimate', 'cutoff') + [
            'completeCount' => $total - $incomplete, 'incompleteCount' => $incomplete,
            'completenessRate' => $total ? round(($total - $incomplete) / $total * 100, 1) : 0,
            'heatPeakDay' => max($days) ? $weekdays[array_search(max($days), $days)] : 'No activity',
            'heatPeakMonth' => max($heatMonths) ? CarbonImmutable::create(2000, array_search(max($heatMonths), $heatMonths), 1)->format('F') : 'No activity',
            'highestCancellationMonth' => $monthly->where('cancelled', '>', 0)->sortByDesc('rate')->first(),
        ];
    }

    private function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    private function clock(int $minutes): string
    {
        return CarbonImmutable::today()->addMinutes($minutes)->format('g:i A');
    }
}
