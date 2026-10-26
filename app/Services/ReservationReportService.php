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
        'no_show' => 'No-show Report',
        'attendance' => 'Attendance & No-show Report',
        'equipment' => 'Equipment Request Report',
    ];

    public function reportFilters(Request $request): array
    {
        return $request->validate([
            'report_type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:new,pending,pencil,validated,waiting_list,approved,rejected,cancelled,completed,no_show'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
        ]);
    }

    public function reportQuery(array $filters): Builder
    {
        return app(DashboardAnalyticsService::class)->records($filters)->with('eventOutcome.recorder')
            ->when($filters['report_type'] === 'attendance', fn ($query) => $query
                ->whereIn('status', ['completed', 'no_show'])
                ->whereHas('eventOutcome', fn ($outcome) => $outcome->whereColumn('outcome', 'reservations.status')))
            ->when($filters['report_type'] === 'cancellation', fn ($query) => $query->where('status', 'cancelled'))
            ->when($filters['report_type'] === 'utilization', fn ($query) => $query->where('status', 'approved'))
            ->when($filters['report_type'] === 'no_show', fn ($query) => $query->where('status', 'no_show'))
            ->when($filters['report_type'] === 'equipment', fn ($query) => $query->whereHas('equipment'));
    }

    public function reportData(array $filters): array
    {
        $query = $this->reportQuery($filters);
        $counts = (clone $query)->reorder()->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $counts->sum();
        $metrics = ['Reservations' => $total];
        $rows = null;
        $note = 'Reporting uses the reservation/event date, including both selected dates. Statuses reflect the current record status.';
        if ($filters['report_type'] === 'attendance') {
            $metrics += ['Attended / Completed' => (int) $counts->get('completed', 0), 'No-show' => (int) $counts->get('no_show', 0),
                'Actual attendees' => (int) \App\Models\ReservationEventOutcome::whereIn('reservation_id', (clone $query)->reorder()->select('reservations.id'))->sum('actual_attendees')];
            $note .= ' Includes recorded completed and no-show outcomes only. Missing attendance is never assumed to be a no-show. Expected attendance is captured when the outcome is first recorded.';
        }
        if ($filters['report_type'] === 'equipment') {
            $rows = (clone $query)->with('equipment')->get()->flatMap(fn ($record) => $record->equipment->map(fn ($item) => [
                $record->reference_number, $record->reservation_date->format('Y-m-d'), $item->equipment_name,
                $item->pivot->quantity_requested, $item->pivot->quantity_approved ?? 0,
                DashboardAnalyticsService::label($item->pivot->status),
            ]));
        } elseif ($filters['report_type'] === 'status') {
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
            $actual = \App\Models\ReservationEventOutcome::where('outcome', 'completed')
                ->whereIn('reservation_id', (clone $query)->reorder()->select('reservations.id')->where('status', 'completed'))
                ->sum('actual_attendees');
            $metrics += ['Approved' => (int) $counts->get('approved', 0), 'Cancelled' => (int) $counts->get('cancelled', 0),
                'Completed' => (int) $counts->get('completed', 0), 'Recorded actual attendees' => (int) $actual];
        }

        return ['title' => self::TYPES[$filters['report_type']], 'filters' => $filters, 'metrics' => $metrics, 'rows' => $rows, 'total' => $total, 'note' => $note,
            'headers' => $filters['report_type'] === 'equipment' ? ['Reference', 'Reservation date', 'Equipment', 'Requested', 'Approved', 'Status'] : ($rows !== null ? [$filters['report_type'] === 'status' ? 'Status' : 'Event type', 'Reservations'] : ['Reference', 'Event', 'Reservation date', 'Start', 'End', 'Facility', 'Status', 'Expected attendees', 'Actual attendees']),
            'generatedAt' => now(),
            ...($filters['report_type'] === 'attendance' ? ['headers' => ['Reference', 'Event', 'Event type', 'Facility', 'Scheduled date', 'Start', 'End', 'Expected attendees', 'Actual attendees', 'Attendance status', 'Recorded by', 'Remarks']] : [])];
    }

    public function reportRow(Reservation $record, string $type = 'reservation'): array
    {
        if ($type === 'attendance') {
            $outcome = $record->eventOutcome;
            return [$record->reference_number, $record->event_name, $record->event_type ?: 'Not specified',
                $record->facility?->facility_name ?? 'Unassigned', $record->reservation_date->format('Y-m-d'),
                substr($record->start_time, 0, 5), substr($record->end_time, 0, 5), $outcome->expected_attendees,
                $outcome->actual_attendees, $outcome->outcome === 'completed' ? 'Attended / Completed' : 'No-show',
                $outcome->recorder?->full_name ?? 'Unknown', $outcome->remarks];
        }
        return [$record->reference_number, $record->event_name, $record->reservation_date->format('Y-m-d'), substr($record->start_time, 0, 5), substr($record->end_time, 0, 5), $record->facility?->facility_name ?? 'Unassigned', DashboardAnalyticsService::label($record->status), $record->expected_attendees,
            $record->eventOutcome && $record->eventOutcome->outcome === $record->status ? $record->eventOutcome->actual_attendees : 'Not recorded'];
    }

    public function exportRows(array $data): iterable
    {
        if ($data['rows'] !== null) {
            yield from $data['rows'];
        } else {
            foreach ($this->reportQuery($data['filters'])->lazy(500) as $record) {
                yield $this->reportRow($record, $data['filters']['report_type']);
            }
        }
    }

    public function filters(Request $request): array
    {
        return $request->validate([
            'report_type' => ['required', 'in:reservation_detail,summary,priority_history'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:new,pending,validated,waiting_list,approved,rejected,cancelled,completed,no_show'],
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
