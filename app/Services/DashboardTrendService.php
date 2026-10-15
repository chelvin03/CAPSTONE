<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

class DashboardTrendService
{
    public function summarize(array $filters, DashboardAnalyticsService $analytics): array
    {
        $now = CarbonImmutable::now();
        $scope = \Illuminate\Support\Arr::except($filters, ['month', 'year', 'period']);
        $daily = $analytics->query($scope, false)->selectRaw('reservation_date, COUNT(*) AS total')
            ->groupBy('reservation_date')->orderBy('reservation_date')->pluck('total', 'reservation_date');
        $year = (int) (is_numeric($filters['year'] ?? null) ? $filters['year'] : CarbonImmutable::parse($filters['date_to'])->year);
        $monthly = $yearly = $patterns = [];
        foreach (range(1, 12) as $month) {
            $monthly[$month] = ['label' => CarbonImmutable::create($year, $month, 1)->format('M'), 'count' => 0];
            $patterns[$month] = ['label' => CarbonImmutable::create(2000, $month, 1)->format('F'), 'count' => 0, 'years' => []];
        }
        $outcomes = [];
        foreach (range(1, 12) as $month) {
            $outcomes[$month] = ['label' => CarbonImmutable::create($year, $month, 1)->format('F'), 'total' => 0, 'approved' => 0, 'disapproved' => 0, 'other' => 0];
        }
        $outcomeRows = $analytics->query(\Illuminate\Support\Arr::except($scope, ['status']), false)
            ->whereYear('reservation_date', $year)->selectRaw('reservation_date, status, COUNT(*) AS total')
            ->groupBy('reservation_date', 'status')->get();
        foreach ($outcomeRows as $row) {
            $month = CarbonImmutable::parse($row->reservation_date)->month;
            $outcomes[$month]['total'] += (int)$row->total;
            $key = in_array($row->status, ['approved', 'completed'], true) ? 'approved' : ($row->status === 'rejected' ? 'disapproved' : 'other');
            $outcomes[$month][$key] += (int)$row->total;
        }
        foreach ($outcomes as &$outcome) {
            $outcome['approved_percent'] = $outcome['total'] ? round($outcome['approved'] / $outcome['total'] * 100, 1) : 0;
            $outcome['disapproved_percent'] = $outcome['total'] ? round($outcome['disapproved'] / $outcome['total'] * 100, 1) : 0;
        }
        unset($outcome);
        $firstYear = $daily->isEmpty() ? $now->year : min($now->year, CarbonImmutable::parse($daily->keys()->first())->year);
        $lastYear = $daily->isEmpty() ? $now->year : max($now->year, CarbonImmutable::parse($daily->keys()->last())->year);
        foreach (range($firstYear, $lastYear) as $annualYear) {
            $yearly[$annualYear] = ['label' => (string) $annualYear, 'count' => 0];
        }
        $monthCounts = [];
        foreach ($daily as $date => $count) {
            $date = CarbonImmutable::parse($date);
            if ($date->year === $year) $monthly[$date->month]['count'] += (int) $count;
            $yearly[$date->year]['count'] += (int) $count;
            $monthCounts[$date->format('Y-m')] = ($monthCounts[$date->format('Y-m')] ?? 0) + (int) $count;
            if ($date->lt($now->startOfMonth())) {
                $patterns[$date->month]['count'] += (int) $count;
                $patterns[$date->month]['years'][$date->year] = true;
            }
        }
        $patterns = collect($patterns)->map(fn ($row) => ['label' => $row['label'], 'count' => $row['count'], 'years' => count($row['years'])])
            ->filter(fn ($row) => $row['count'] > 0)->sortByDesc('count')->values()->take(3);
        $history = [];
        $firstDate = $daily->isEmpty() ? null : CarbonImmutable::parse($daily->keys()->first())->startOfMonth();
        for ($month = $now->startOfMonth()->subMonths(6); $month->lt($now->startOfMonth()); $month = $month->addMonth()) {
            if ($firstDate && $month->gte($firstDate)) $history[] = $monthCounts[$month->format('Y-m')] ?? 0;
        }
        $estimate = count($history) >= 3 ? round(array_sum($history) / count($history), 1) : null;
        $forecast = [];
        for ($offset = 1; $offset <= 3; $offset++) {
            $forecast[] = ['label' => $now->startOfMonth()->addMonths($offset)->format('F Y'), 'estimate' => $estimate];
        }
        return ['dashboardMonthlyOutcomes' => array_values($outcomes), 'dashboardMonthlyTrend' => array_values($monthly), 'dashboardTrendYear' => $year,
            'dashboardYearlyTrend' => array_values($yearly), 'dashboardBusyMonths' => $patterns,
            'dashboardForecast' => $forecast, 'dashboardForecastMonths' => count($history),
            'dashboardForecastFrom' => $now->startOfMonth()->subMonths(count($history))->format('M Y'),
            'dashboardForecastTo' => $now->startOfMonth()->subMonth()->format('M Y')];
    }
}
