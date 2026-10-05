<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReservationReportService
{
    public const TYPES = [
        'reservation' => 'Reservation Report',
        'utilization' => 'Gymnasium Utilization Report',
        'status' => 'Reservation Status Report',
        'event_type' => 'Event Type Report',
        'cancellation' => 'Cancellation Report',
    ];

    public function reportFilters(Request $request): array
    {
        return $request->validate([
            'report_type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
    }

    public function reportQuery(array $filters): Builder
    {
        return app(DashboardAnalyticsService::class)->records($filters)
            ->when($filters['report_type'] === 'cancellation', fn ($query) => $query->where('status', 'cancelled'))
            ->when($filters['report_type'] === 'utilization', fn ($query) => $query->where('status', 'approved'));
    }

    public function reportData(array $filters): array
    {
        $query = $this->reportQuery($filters);
        $counts = (clone $query)->reorder()->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $counts->sum();
        $metrics = ['Reservations' => $total];
        $rows = null;
        $note = 'Reporting uses the reservation/event date, including both selected dates. Statuses reflect the current record status.';
        if ($filters['report_type'] === 'status') {
            $statuses = array_unique(array_merge(['new', 'validated', 'approved', 'rejected', 'waiting_list', 'cancelled'], $counts->keys()->all()));
            $rows = collect($statuses)->map(fn ($status) => [DashboardAnalyticsService::label($status), (int) $counts->get($status, 0)]);
        } elseif ($filters['report_type'] === 'event_type') {
            $rows = (clone $query)->reorder()->select([])->selectRaw("COALESCE(event_type, '') AS event_type, COUNT(*) AS total")
                ->groupByRaw("COALESCE(event_type, '')")->orderBy('event_type')->get()
                ->map(fn ($record) => [$record->event_type ?: 'Not specified', (int) $record->total]);
        } elseif ($filters['report_type'] === 'utilization') {
            $usage = app(DashboardAnalyticsService::class)->reportUtilization($filters);
            $metrics += ['Approved booked hours' => $usage['utilizedHours'] ?? 'Unavailable', 'Bookable hours' => $usage['availableHours'] ?? 'Unavailable', 'Utilization' => $usage['utilizationRate'] === null ? 'Unavailable' : $usage['utilizationRate'].'%'];
            $note .= ' Approved time intervals are merged per facility/day, clipped to configured operating hours, and reduced by schedule closures. Capacity covers all facilities and calendar days, matching the dashboard. Percentage is unavailable when hours are invalid or capacity is zero.';
        } elseif ($filters['report_type'] === 'reservation') {
            $metrics += ['Approved' => (int) $counts->get('approved', 0), 'Cancelled' => (int) $counts->get('cancelled', 0)];
        }

        return ['title' => self::TYPES[$filters['report_type']], 'filters' => $filters, 'metrics' => $metrics, 'rows' => $rows, 'total' => $total, 'note' => $note,
            'headers' => $rows !== null ? [$filters['report_type'] === 'status' ? 'Status' : 'Event type', 'Reservations'] : ['Reference', 'Event', 'Reservation date', 'Start', 'End', 'Facility', 'Status'],
            'generatedAt' => now()];
    }

    public function reportRow(Reservation $record): array
    {
        return [$record->reference_number, $record->event_name, $record->reservation_date->format('Y-m-d'), substr($record->start_time, 0, 5), substr($record->end_time, 0, 5), $record->facility?->facility_name ?? 'Unassigned', DashboardAnalyticsService::label($record->status)];
    }

    public function exportRows(array $data): iterable
    {
        if ($data['rows'] !== null) {
            yield from $data['rows'];
        } else {
            foreach ($this->reportQuery($data['filters'])->lazy(500) as $record) {
                yield $this->reportRow($record);
            }
        }
    }

    public function filters(Request $request): array
    {
        return $request->validate([
            'report_type' => ['required', 'in:reservation_detail,summary,priority_history'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:new,pending,validated,waiting_list,approved,rejected,cancelled,completed'],
            'category' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    public function records(array $filters): Collection
    {
        return Reservation::query()->with(['facility', 'user'])
            ->when(($filters['report_type'] ?? '') === 'priority_history', fn (Builder $query) => $query->whereNotNull('priority_number'))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('reservation_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('reservation_date', '<', \Carbon\CarbonImmutable::parse($date)->addDay()->toDateString()))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('reservation_type', $category))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->orderBy('reservation_date')->orderBy('start_time')->orderBy('id')->get();
    }

    public function summary(Collection $records): array
    {
        $total = $records->count();
        return [
            'total' => $total,
            'approved' => $records->where('status', 'approved')->count(),
            'cancelled' => $records->where('status', 'cancelled')->count(),
            'completed' => $records->where('status', 'completed')->count(),
            'attendees' => (int) $records->sum('expected_attendees'),
            'cancellation_rate' => $total > 0 ? $records->where('status', 'cancelled')->count() / $total : 0,
        ];
    }
}
