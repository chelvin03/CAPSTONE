<?php

namespace App\Services;

use App\Models\SystemSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class DashboardKpiService
{
    public const TARGETS = [
        'utilization' => ['label' => 'Minimum scheduled utilization', 'unit' => '%', 'max' => 100, 'direction' => 'min'],
        'review_hours' => ['label' => 'Maximum average decision time', 'unit' => 'hours', 'max' => 720, 'direction' => 'max'],
        'overdue' => ['label' => 'Maximum pending requests aged 7+ days', 'unit' => 'requests', 'max' => 10000, 'direction' => 'max'],
        'cancellations' => ['label' => 'Maximum cancellation rate', 'unit' => '%', 'max' => 100, 'direction' => 'max'],
        'event_completion' => ['label' => 'Minimum recorded event completion rate', 'unit' => '%', 'max' => 100, 'direction' => 'min'],
        'no_show' => ['label' => 'Maximum recorded no-show rate', 'unit' => '%', 'max' => 100, 'direction' => 'max'],
        'outcome_coverage' => ['label' => 'Minimum event-outcome recording coverage', 'unit' => '%', 'max' => 100, 'direction' => 'min'],
    ];

    public function summarize(array $data, DashboardAnalyticsService $analytics): array
    {
        $filters = $data['filters'];
        $records = $analytics->query($filters)->with('statusHistories')->get();
        $current = $this->snapshot($records) + ['utilization' => $data['utilizationRate']];
        $results = $data['eventResults'] ?? app(EventOutcomeAnalyticsService::class)->summarize($filters, $analytics)['eventResults'];
        $current += ['event_completion' => $results['completionRate'], 'no_show' => $results['noShowRate'], 'outcome_coverage' => $results['coverage']];
        $comparable = ! ($filters['all_time'] ?? false) && !(($filters['year'] ?? '') === 'all' && isset($filters['month']));
        $previous = null;
        if ($comparable) {
            $scope = array_merge(Arr::except($filters, ['all_time', 'year', 'month', 'day']), [
                'date_from' => $data['previousFrom']->toDateString(), 'date_to' => $data['previousTo']->toDateString(),
            ]);
            $previous = $this->snapshot($analytics->query($scope)->with('statusHistories')->get());
            $previous['utilization'] = $analytics->reportUtilization($scope)['utilizationRate'];
            $previousResults = app(EventOutcomeAnalyticsService::class)->summarize($scope, $analytics)['eventResults'];
            $previous += ['event_completion' => $previousResults['completionRate'], 'no_show' => $previousResults['noShowRate'], 'outcome_coverage' => $previousResults['coverage']];
        }
        $settings = SystemSetting::where('key', 'like', 'kpi_%')->pluck('value', 'key');
        $overdue = $records->filter(fn ($r) => in_array($r->status, ['new', 'validated', 'pending'], true)
            && $r->created_at->lte(now()->subDays(7)));
        $cards = [];
        foreach ([
            'utilization' => ['Scheduled Gym Utilization', '%', 'Approved hours / bookable operating hours; blocks excluded. Includes future dates in the selected period. Not actual attendance.', $records->where('status', 'approved')],
            'review_hours' => ['Average Decision Time', 'hours', 'Submission to first valid recorded approval, rejection or cancellation. '.$current['review_sample'].' valid decisions; excludes requests with no valid timestamp.', $records->whereIn('id', $current['review_ids'])],
            'overdue' => ['Overdue Pending Requests', 'requests', 'New, pending or validated requests submitted at least 7 days ago, measured as of now. This is a review flag, not an automatic rejection.', $overdue],
            'cancellations' => ['Cancellation Rate', '%', 'Currently cancelled requests / all matching requests, grouped by scheduled event dates. Includes requestor and administrator cancellations.', $records->where('status', 'cancelled')],
            'event_completion' => ['Recorded Event Completion', '%', 'Verified completed outcomes / all verified completed and no-show outcomes for ended events. Cancelled, rejected, future and unapproved requests are excluded. Review outcome coverage before interpreting this rate.', $results['completed']],
            'no_show' => ['Recorded No-show Rate', '%', 'Verified no-show outcomes / all verified completed and no-show outcomes for ended events. Cancellation is a different outcome. Missing records are not assumed to be no-shows.', $results['noShow']],
            'outcome_coverage' => ['Event Outcome Coverage', '%', 'Verified outcomes / ended approved, completed and no-show events matching the filters. Legacy completed statuses without matching records remain unverified.', $results['unknown']],
        ] as $key => [$title, $unit, $definition, $evidence]) {
            $value = $key === 'overdue' ? $overdue->count() : $current[$key];
            $raw = $settings['kpi_'.$key] ?? null;
            $target = is_numeric($raw) ? (float) $raw : null;
            $met = $value === null || $target === null ? null
                : (self::TARGETS[$key]['direction'] === 'min' ? $value >= $target : $value <= $target);
            $baseline = $previous[$key] ?? null;
            $comparison = $key === 'overdue' ? 'Historical backlog snapshots are unavailable; no trend claimed.'
                : (! $previous || ! $previous['total'] ? 'No comparison data in the previous event-date period.'
                : ($value === null || $baseline === null ? 'Insufficient valid data for comparison.'
                : $this->comparison($value, $baseline, $unit)));
            $cards[] = compact('key', 'title', 'unit', 'value', 'definition', 'target', 'met', 'comparison', 'evidence')
                + ['direction' => self::TARGETS[$key]['direction']];
        }
        $support = [];
        $issueIds = collect($data['issues'])->except('Event type not recorded (optional)')->flatten(1)->pluck('id')->unique();
        foreach ([
            ['Total Reservations', $records->count(), 'All matching requests in the selected event-date period.', $records],
            ['Approved Reservations', $records->where('status', 'approved')->count(), 'Currently approved; completed events are a separate status.', $records->where('status', 'approved')],
            ['Pending Review', $records->whereIn('status', ['new', 'validated', 'pending'])->count(), 'New, pending and validated requests; pencil/waitlisted requests are shown separately in reservation management.', $records->whereIn('status', ['new', 'validated', 'pending'])],
            ['Required-field Completeness', $records->isEmpty() ? null : $data['completenessRate'].'%', 'Checks required event/contact/schedule fields, attendee validity, and required public approval letters. Optional event type is excluded.', $records->whereIn('id', $issueIds)],
            ['Event-type Coverage', $current['coverage'] === null ? null : $current['coverage'].'%', $records->filter(fn ($r) => filled($r->event_type))->count().' of '.$records->count().' requests have an event type. Missing event types limit the event-type chart.', $records->filter(fn ($r) => blank($r->event_type))],
        ] as [$title, $value, $definition, $evidence]) $support[] = compact('title', 'value', 'definition', 'evidence');

        return ['kpiCards' => $cards, 'kpiContextCards' => $support, 'kpiTargets' => $settings,
            'kpiScopeNote' => 'Selected event dates: '.$filters['date_from'].' to '.$filters['date_to'].'. '
                .(CarbonImmutable::parse($filters['date_to'])->endOfDay()->isFuture() ? 'Includes future scheduled dates; utilization is a planning measure.' : 'Scheduled use is not measured physical attendance.'),
            'kpiComparisonNote' => $comparable ? 'Compared with '.$data['previousFrom']->toDateString().' to '.$data['previousTo']->toDateString().', an equal-length window with the same event, status, requestor and facility filters. Statuses reflect the records as they are now, not historical snapshots.' : 'Previous-period comparison is unavailable for this reporting scope.',
            'kpiSampleNote' => $records->count() < 5 ? 'Limited sample: fewer than 5 matching requests. Avoid firm conclusions.' : null];
    }

    private function snapshot($records): array
    {
        $durations = [];
        foreach ($records as $record) {
            $first = collect([$record->approved_at, $record->rejected_at, $record->cancelled_at])
                ->merge($record->statusHistories->whereIn('new_status', ['approved', 'rejected', 'cancelled'])->pluck('created_at'))
                ->filter(fn ($date) => $date && $date->gte($record->created_at))->sort()->first();
            if ($first) $durations[$record->id] = $record->created_at->diffInSeconds($first) / 3600;
        }
        return ['total' => $records->count(), 'review_ids' => array_keys($durations), 'review_sample' => count($durations),
            'review_hours' => $durations ? round(array_sum($durations) / count($durations), 1) : null,
            'coverage' => $records->count() ? round($records->filter(fn ($r) => filled($r->event_type))->count() / $records->count() * 100, 1) : null,
            'cancellations' => $records->count() ? round($records->where('status', 'cancelled')->count() / $records->count() * 100, 1) : null];
    }

    private function comparison(float $current, float $previous, string $unit): string
    {
        $change = round($current - $previous, 1);
        return ($change > 0 ? '+' : '').$change.($unit === '%' ? ' percentage points' : ' hours')
            .' vs previous '.$previous.($unit === '%' ? '%' : ' hours').'.';
    }
}
