<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsWorkspaceService
{
    public function summarize(array $filters): array
    {
        $analytics = app(DashboardAnalyticsService::class);
        $data = $analytics->summarize($filters)
            + app(DashboardPerformanceService::class)->summarize($filters, $analytics)
            + app(DashboardInsightService::class)->summarize($filters, $analytics)
            + app(EventOutcomeAnalyticsService::class)->summarize($filters, $analytics);
        $records = $analytics->query($filters)->get();
        $feedback = DB::table('feedback')->whereIn('reservation_id', $records->pluck('id'))->get(['reservation_id', 'rating']);
        $equipment = DB::table('reservation_equipment')->join('equipment', 'equipment.id', '=', 'reservation_equipment.equipment_id')
            ->whereIn('reservation_id', $records->pluck('id'))
            ->selectRaw('equipment_name, SUM(quantity_requested) as requested, SUM(COALESCE(quantity_approved, 0)) as allocated')
            ->groupBy('equipment.id', 'equipment_name')->orderByDesc('requested')->get();
        $months = collect($data['performance']['months'])->map(function ($month) use ($feedback) {
            $rows = $month['records'];
            $approvalHours = $rows->filter(fn ($r) => $r->approved_at && $r->created_at && $r->approved_at->gte($r->created_at))
                ->map(fn ($r) => $r->created_at->diffInSeconds($r->approved_at) / 3600);
            $ratings = $feedback->whereIn('reservation_id', $rows->pluck('id'));

            return $month + ['total' => $rows->count(), 'approved' => $rows->where('status', 'approved')->count(),
                'cancelled' => $rows->where('status', 'cancelled')->count(),
                'approvalDays' => $approvalHours->isEmpty() ? null : round($approvalHours->avg() / 24, 2),
                'satisfaction' => $ratings->isEmpty() ? null : round($ratings->avg('rating'), 2), 'responses' => $ratings->count()];
        });
        $approvalHours = $records->filter(fn ($r) => $r->approved_at && $r->created_at && $r->approved_at->gte($r->created_at))
            ->map(fn ($r) => $r->created_at->diffInSeconds($r->approved_at) / 3600);
        $verifiedCompletedIds = $data['eventResults']['completed']->pluck('id');
        $verifiedOutcomeIds = $data['eventResults']['recorded']->pluck('id');
        $activities = $records->groupBy(fn ($r) => json_encode([$r->event_name, $r->reservation_type, $r->event_type]))
            ->map(fn ($rows) => ['name' => $rows->first()->event_name ?: 'Unnamed activity',
                'group' => DashboardAnalyticsService::label($rows->first()->reservation_type),
                'type' => DashboardAnalyticsService::label($rows->first()->event_type),
                'count' => $rows->count(), 'approved' => $rows->where('status', 'approved')->count(),
                'cancelled' => $rows->where('status', 'cancelled')->count(),
                'completionRate' => $rows->whereIn('id', $verifiedOutcomeIds)->count()
                    ? round($rows->whereIn('id', $verifiedCompletedIds)->count() / $rows->whereIn('id', $verifiedOutcomeIds)->count() * 100, 1) : null,
            ])->sortByDesc('count')->take(5)->values();
        // Raw scheduled booking hours are deliberately separate from merged utilization hours.
        $weekdayHours = collect(range(1, 7))->map(fn ($day) => [
            'label' => CarbonImmutable::create(2026, 1, 5)->addDays($day - 1)->format('D'),
            'count' => round($records->where('status', 'approved')->filter(fn ($r) => $r->reservation_date->dayOfWeekIso === $day)
                ->sum(fn ($r) => $this->hours($r)), 2),
        ]);

        return $data + compact('months', 'activities', 'weekdayHours', 'equipment') + [
            'approvalDays' => $approvalHours->isEmpty() ? null : round($approvalHours->avg() / 24, 2),
            'approvalSample' => $approvalHours->count(),
            'equipmentFulfillment' => $equipment->sum('requested') > 0 ? round($equipment->sum('allocated') / $equipment->sum('requested') * 100, 1) : null,
            'unusedHours' => round($data['eventResults']['noShow']->sum(fn ($r) => $this->hours($r)), 2),
        ];
    }

    private function hours($reservation): float
    {
        $date = $reservation->reservation_date->toDateString();

        return max(0, CarbonImmutable::parse($date.' '.$reservation->start_time)
            ->diffInSeconds(CarbonImmutable::parse($date.' '.$reservation->end_time), false) / 3600);
    }
}
