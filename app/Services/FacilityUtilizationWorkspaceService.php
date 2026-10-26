<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class FacilityUtilizationWorkspaceService
{
    public function summarize(array $data, array $options): array
    {
        $analytics = app(DashboardAnalyticsService::class);
        $filters = $data['filters'];
        $from = CarbonImmutable::parse($filters['date_from']);
        $to = CarbonImmutable::parse($filters['date_to']);
        $fullMonth = $from->day === 1 && $to->equalTo($from->endOfMonth()->startOfDay());
        $previousTo = $from->subDay();
        $previousFrom = $fullMonth ? $previousTo->startOfMonth() : $from->subDays((int) $from->diffInDays($to) + 1);
        $previousFilters = Arr::except($filters, ['year', 'month', 'day', 'all_time']);
        $previousFilters['date_from'] = $previousFrom->toDateString();
        $previousFilters['date_to'] = $previousTo->toDateString();
        $previous = $analytics->reportUtilization($previousFilters);
        $previousOutcomes = app(EventOutcomeAnalyticsService::class)->summarize($previousFilters, $analytics)['eventResults'];
        $duration = fn ($r) => max(0, CarbonImmutable::parse($r->reservation_date->toDateString().' '.$r->start_time)->diffInSeconds(CarbonImmutable::parse($r->reservation_date->toDateString().' '.$r->end_time), false) / 3600);
        $previousUnused = $previousOutcomes['noShow']->sum($duration);
        $delta = fn ($value, $before) => $value === null || $before === null || $before == 0 ? null : round(($value - $before) / $before * 100, 1);
        $changes = [
            'available' => $delta($data['availableHours'], $previous['availableHours']),
            'booked' => $delta($data['utilizedHours'], $previous['utilizedHours']),
            'utilization' => $delta($data['utilizationRate'], $previous['utilizationRate']),
            'unused' => $delta($data['unusedHours'], $previousUnused),
        ];
        $heat = $data['utilizationHeat'] ?? [];
        $peak = collect($heat)->sortByDesc(fn ($slot) => array_sum($slot['counts']))->first();
        $peakHours = $peak ? round(array_sum($peak['counts']), 2) : 0;
        $peakLabel = $peakHours > 0 ? $peak['label'] : 'Not available';
        $weekdays = $data['utilizationWeekdays'] ?? collect(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])->map(fn ($label) => ['label' => $label, 'count' => null]);
        if (($options['weekday_metric'] ?? 'hours') === 'percentage') {
            $weekdays = $weekdays->map(fn ($row) => ['label' => $row['label'], 'count' => $data['utilizedHours'] ? round($row['count'] / $data['utilizedHours'] * 100, 1) : null]);
        }
        if (($options['occupancy'] ?? 'hour') === 'period') {
            $heat = collect($heat)->chunk(2)->map(fn ($slots) => ['start' => $slots->first()['start'], 'end' => $slots->last()['end'],
                'label' => $slots->first()['label'].' / '.$slots->last()['label'],
                'counts' => array_map(fn ($index) => round($slots->sum(fn ($slot) => $slot['counts'][$index]), 2), range(0, 6)),
            ])->values()->all();
        }
        $months = $data['months'];
        if (($options['trend'] ?? 'monthly') === 'quarterly') {
            $months = $months->groupBy(fn ($month) => CarbonImmutable::createFromFormat('!M Y', $month['label'])->format('Y').'-Q'.CarbonImmutable::createFromFormat('!M Y', $month['label'])->quarter)
                ->map(function ($rows, $label) {
                    $available = $rows->whereNotNull('availableHours')->isEmpty() ? null : $rows->sum('availableHours');
                    $booked = $rows->whereNotNull('utilizedHours')->isEmpty() ? null : $rows->sum('utilizedHours');

                    return ['label' => $label, 'availableHours' => $available, 'utilizedHours' => $booked, 'utilizationRate' => $available ? round($booked / $available * 100, 1) : null];
                })->values();
        }
        $records = $analytics->query($filters)->with('statusHistories')->get();
        if (($options['outcomes'] ?? 'all') === 'ended') {
            $records = $records->filter(fn ($r) => ! app(EventOutcomeService::class)->endAt($r)->isFuture());
        }
        $outcomeRows = collect(['Approved', 'Completed (Pending Usage)', 'Cancelled', 'No Show', 'Rescheduled', 'Other'])->map(fn ($label) => ['label' => $label, 'count' => 0])->keyBy('label');
        foreach ($records as $record) {
            $key = match ($record->status) {
                'completed' => 'Completed (Pending Usage)', 'cancelled' => 'Cancelled', 'no_show' => 'No Show',
                default => $record->statusHistories->contains(fn ($h) => str_starts_with($h->remarks ?? '', 'Reservation rescheduled:')) ? 'Rescheduled' : ($record->status === 'approved' ? 'Approved' : 'Other'),
            };
            $row = $outcomeRows[$key];
            $row['count']++;
            $outcomeRows[$key] = $row;
        }
        $allRecords = $analytics->query($filters)->get();
        $asOf = $to->min(CarbonImmutable::today())->toDateString();
        $noteDate = fn ($rows) => $rows->map(fn ($r) => $r->reservation_date->toDateString())->max() ?? $asOf;
        $notes = collect([
            ['date' => $asOf, 'note' => 'Actual usage data is not yet available.', 'type' => 'Information', 'value' => '—'],
            ['date' => $noteDate($allRecords->where('status', 'approved')), 'note' => 'Approved booked hours in the selected period.', 'type' => 'Observation', 'value' => $data['utilizedHours'] === null ? 'Not available' : $data['utilizedHours'].' hrs'],
            ['date' => $noteDate($data['eventResults']['noShow']), 'note' => 'Unused booked hours from verified no-show outcomes.', 'type' => 'Notice', 'value' => $data['unusedHours'].' hrs'],
            ['date' => $noteDate($allRecords->where('status', 'approved')), 'note' => 'Peak scheduled usage period: '.$peakLabel.'.', 'type' => 'Observation', 'value' => $peakHours.' hrs'],
            ['date' => $noteDate($allRecords->where('status', 'approved')->filter(fn ($r) => $r->reservation_date->isWeekend())), 'note' => 'Weekend booked hours in the selected period.', 'type' => 'Information', 'value' => round(($data['utilizationWeekdays'] ?? collect())->slice(5)->sum('count'), 2).' hrs'],
        ]);
        $notes = ($options['notes_order'] ?? 'latest') === 'oldest' ? $notes->sortBy('date') : $notes->sortByDesc('date');

        return compact('changes', 'peakHours', 'peakLabel', 'heat', 'weekdays', 'months', 'outcomeRows', 'notes') + [
            'comparisonLabel' => $fullMonth ? 'vs. previous month' : 'vs. previous period', 'options' => $options,
        ];
    }
}
