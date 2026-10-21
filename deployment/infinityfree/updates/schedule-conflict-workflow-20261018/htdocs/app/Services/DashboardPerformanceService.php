<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class DashboardPerformanceService
{
    public function summarize(array $filters, DashboardAnalyticsService $analytics): array
    {
        $records = $analytics->query($filters)->with(['statusHistories', 'cancelledBy'])->get();
        $duration = [];
        $pending = ['Under 2 days' => collect(), '2–6 days' => collect(), '7+ days' => collect()];
        $sources = ['Requestor' => collect(), 'Administrator' => collect(), 'Unknown / legacy' => collect()];
        $reasons = [];
        foreach ($records as $record) {
            $decisions = collect([$record->approved_at, $record->rejected_at, $record->cancelled_at])
                ->merge($record->statusHistories->whereIn('new_status', ['approved', 'rejected', 'cancelled'])->pluck('created_at'))
                ->filter(fn ($date) => $date && $date->gte($record->created_at))->sort();
            if ($decisions->isNotEmpty()) {
                $duration[$record->id] = round($record->created_at->diffInSeconds($decisions->first()) / 3600, 2);
            }
            if (in_array($record->status, ['new', 'validated', 'pending'], true)) {
                $age = max(0, $record->created_at->diffInHours(now()));
                $pending[$age < 48 ? 'Under 2 days' : ($age < 168 ? '2–6 days' : '7+ days')]->push($record);
            }
            if ($record->status !== 'cancelled') continue;
            $requestor = $record->statusHistories->contains(fn ($h) => $h->new_status === 'cancelled'
                && str_starts_with($h->remarks ?? '', 'Cancelled by requestor:'));
            $source = $requestor ? 'Requestor' : ($record->cancelled_by ? 'Administrator' : 'Unknown / legacy');
            $sources[$source]->push($record);
            $reason = trim($record->cancellation_reason ?? '') ?: 'No reason recorded';
            $reasons[$reason] ??= collect();
            $reasons[$reason]->push($record);
        }
        $values = collect($duration)->sort()->values();
        $bands = collect(['Under 24 hours' => [0, 24], '24–72 hours' => [24, 72], '72+ hours' => [72, INF]])
            ->map(fn ($range) => $records->filter(fn ($r) => isset($duration[$r->id])
                && $duration[$r->id] >= $range[0] && $duration[$r->id] < $range[1]));
        $from = CarbonImmutable::parse($filters['date_from']);
        $to = CarbonImmutable::parse($filters['date_to']);
        $trendFrom = $from->max($to->startOfMonth()->subMonths(11));
        $months = [];
        for ($month = $trendFrom->startOfMonth(); $month->lte($to); $month = $month->addMonth()) {
            if (isset($filters['month']) && $month->month !== (int) $filters['month']) continue;
            $start = $month->max($from);
            $end = $month->endOfMonth()->min($to);
            $scope = array_merge(Arr::except($filters, ['all_time', 'year', 'month', 'day']),
                ['date_from' => $start->toDateString(), 'date_to' => $end->toDateString()]);
            $monthly = $records->filter(fn ($r) => $r->reservation_date->format('Y-m') === $month->format('Y-m'));
            $months[] = ['label' => $month->format('M Y'), 'records' => $monthly,
                'cancellationRate' => $monthly->count() ? round($monthly->where('status', 'cancelled')->count() / $monthly->count() * 100, 1) : null,
                ...$analytics->reportUtilization($scope)];
        }
        $approved = $records->filter(fn ($r) => in_array($r->status, ['approved', 'completed', 'no_show'], true)
            || $r->approved_at || $r->statusHistories->contains('new_status', 'approved'))->count();
        return ['performance' => ['months' => $months, 'trendLimited' => $trendFrom->gt($from),
            'processingMedian' => $values->isEmpty() ? null : round($values->median(), 1),
            'processingAverage' => $values->isEmpty() ? null : round($values->avg(), 1),
            'processingSample' => $values->count(), 'processingMissing' => $records->count() - $values->count(),
            'approvalRate' => $records->count() ? round($approved / $records->count() * 100, 1) : null,
            'rejectionRate' => $records->count() ? round($records->where('status', 'rejected')->count() / $records->count() * 100, 1) : null,
            'bands' => $bands, 'pending' => collect($pending), 'sources' => collect($sources),
            'reasons' => collect($reasons)->sortByDesc(fn ($rows) => $rows->count())]];
    }
}
