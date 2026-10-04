<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardAnalyticsService
{
    public const STATUS_COLORS = [
        'new' => '#2563eb', 'validated' => '#7c3aed', 'approved' => '#10b981',
        'rejected' => '#ef4444', 'waiting_list' => '#f59e0b', 'cancelled' => '#64748b',
        'completed' => '#0f766e', 'pending' => '#64748b',
    ];

    public function query(array $filters, bool $withDates = true): Builder
    {
        return Reservation::query()
            ->when($filters['facility_id'] ?? null, fn ($q, $value) => $q->where('facility_id', $value))
            ->when($filters['category'] ?? null, fn ($q, $value) => $q->where('event_type', $value))
            ->when($filters['requestor_type'] ?? null, fn ($q, $value) => $q->where('reservation_type', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['month'] ?? null, fn ($q, $value) => $q->whereMonth('reservation_date', $value))
            ->when($withDates, fn ($q) => $q->where('reservation_date', '>=', $filters['date_from'])
                ->where('reservation_date', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->toDateString()));
    }

    public function records(array $filters): Builder
    {
        return $this->query($filters)->select([
            'id', 'reference_number', 'event_name', 'event_type', 'reservation_type', 'facility_id',
            'reservation_date', 'start_time', 'end_time', 'expected_attendees', 'status',
        ])->with('facility:id,facility_name')->orderBy('reservation_date')->orderBy('start_time')->orderBy('id');
    }

    public static function label(?string $value): string
    {
        return $value === null || trim($value) === '' ? 'Not specified' : Str::title(str_replace('_', ' ', trim($value)));
    }

    public function summarize(array $filters): array
    {
        $query = $this->query($filters);
        $now = CarbonImmutable::now();
        $from = CarbonImmutable::parse($filters['date_from']);
        $to = CarbonImmutable::parse($filters['date_to']);
        $counts = (clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $counts->sum();
        $statuses = array_values(array_unique(array_merge(['new', 'validated', 'approved', 'rejected', 'waiting_list', 'cancelled'], $counts->keys()->all())));
        $oldPending = (clone $query)->whereIn('status', ['new', 'validated'])->where('created_at', '<=', $now->subDays(7))->count();
        $previousTo = $from->subDay();
        $previousFrom = $from->subDays((int) $from->diffInDays($to) + 1);
        $previousTotal = $this->query(array_merge($filters, ['date_from' => $previousFrom->toDateString(), 'date_to' => $previousTo->toDateString()]))->count();

        // Aggregate by reservation date; all-years filters may span more than three years.
        $daily = (clone $query)->selectRaw('reservation_date, COUNT(*) AS total')->groupBy('reservation_date')->orderBy('reservation_date')->pluck('total', 'reservation_date');
        $monthlyActivity = $this->trend($daily, $from, $to, 'monthly');
        $trendActivity = $this->trend($daily, $from, $to, $filters['grouping']);
        $weekdayCounts = array_fill(1, 7, 0);
        foreach ($daily as $date => $count) {
            $weekdayCounts[CarbonImmutable::parse($date)->dayOfWeekIso] += (int) $count;
        }
        $weekdayDemand = collect(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])
            ->map(fn ($label, $index) => ['label' => $label, 'count' => $weekdayCounts[$index + 1]]);
        $eventDemand = (clone $query)->selectRaw("COALESCE(event_type, '') AS category, COUNT(*) AS total")
            ->groupBy('category')->orderByDesc('total')->orderBy('category')->get()
            ->map(fn ($row) => ['category' => $row->category, 'label' => self::label($row->category), 'count' => (int) $row->total, 'percentage' => $total ? round($row->total / $total * 100, 1) : null]);
        $requestorDemand = (clone $query)->selectRaw('reservation_type, COUNT(*) AS total')->groupBy('reservation_type')->orderByDesc('total')->get()
            ->map(fn ($row) => ['label' => self::label($row->reservation_type), 'count' => (int) $row->total]);
        $facilities = Facility::orderBy('facility_name')->get(['id', 'facility_name']);
        $operating = $this->operatingHours();
        $utilization = $this->utilization($query, $operating, $from, $to, $facilities, $filters);
        $processing = $this->processing($query);
        $timeSlots = $this->timeSlots($query);
        $extras = $this->supplementary($query);
        $filterLabels = [
            'Event type' => isset($filters['category']) ? self::label($filters['category']) : 'All',
            'Requestor type' => isset($filters['requestor_type']) ? self::label($filters['requestor_type']) : 'All',
            'Status' => isset($filters['status']) ? self::label($filters['status']) : 'All',
            'Facility' => isset($filters['facility_id']) ? $facilities->firstWhere('id', $filters['facility_id'])?->facility_name : 'All',
            'Trend grouping' => self::label($filters['grouping']),
        ];

        return array_merge($extras, $utilization, [
            'filters' => $filters, 'filterLabels' => $filterLabels, 'generatedAt' => $now,
            'years' => Reservation::select('reservation_date')->distinct()->pluck('reservation_date')
                ->map(fn ($date) => (int) CarbonImmutable::parse($date)->year)->push((int) $now->year)->unique()->sortDesc()->values(),
            'mostCommonEventType' => $eventDemand->first()['label'] ?? 'N/A',
            'statusOptions' => array_values(array_unique(array_merge(['new', 'validated', 'approved', 'rejected', 'waiting_list', 'cancelled'], Reservation::distinct()->pluck('status')->all()))),
            'statuses' => $statuses, 'statusColors' => self::STATUS_COLORS, 'facilities' => $facilities,
            'categories' => Reservation::whereNotNull('event_type')->where('event_type', '<>', '')->distinct()->orderBy('event_type')->pluck('event_type'),
            'requestorTypes' => Reservation::distinct()->orderBy('reservation_type')->pluck('reservation_type'),
            'totalReservations' => $total, 'approvedReservations' => (int) $counts->get('approved', 0),
            'completedEvents' => (int) $counts->get('completed', 0),
            'pendingReservations' => (int) $counts->get('new', 0) + (int) $counts->get('validated', 0),
            'cancelledReservations' => (int) $counts->get('cancelled', 0), 'oldPending' => $oldPending,
            'cancellationRate' => $total ? round($counts->get('cancelled', 0) / $total * 100, 1) : null,
            'expectedAttendees' => (int) (clone $query)->whereIn('status', ['approved', 'completed'])->sum('expected_attendees'),
            'processingHours' => $processing->hours === null ? null : round((float) $processing->hours, 1),
            'processingCount' => (int) $processing->total,
            'monthlyActivity' => $monthlyActivity, 'trendActivity' => $trendActivity,
            'trendMaximum' => max(1, $trendActivity->max('count')),
            'eventDemand' => $eventDemand, 'requestorDemand' => $requestorDemand,
            'weekdayDemand' => $weekdayDemand, 'timeSlots' => $timeSlots,
            'peakWeekday' => $this->peaks($weekdayDemand), 'peakTimeSlot' => $this->peaks($timeSlots),
            'busiestMonth' => $this->peaks($monthlyActivity->map(fn ($row) => array_merge($row, ['label' => $row['label'].' '.$row['year']]))),
            'statusBreakdown' => collect($statuses)->map(fn ($status) => ['status' => $status, 'count' => (int) $counts->get($status, 0), 'percentage' => $total ? round($counts->get($status, 0) / $total * 100, 1) : null]),
            'previousTotal' => $previousTotal, 'previousFrom' => $previousFrom, 'previousTo' => $previousTo,
            'change' => $previousTotal ? round(($total - $previousTotal) / $previousTotal * 100, 1) : null,
            'operatingHours' => $operating,
        ]);
    }

    private function trend(Collection $daily, CarbonImmutable $from, CarbonImmutable $to, string $grouping): Collection
    {
        $start = fn (CarbonImmutable $date) => match ($grouping) {
            'daily' => $date, 'weekly' => $date->startOfWeek(), 'quarterly' => $date->startOfQuarter(),
            'annual' => $date->startOfYear(), default => $date->startOfMonth(),
        };
        $next = fn (CarbonImmutable $date) => match ($grouping) {
            'daily' => $date->addDay(), 'weekly' => $date->addWeek(), 'quarterly' => $date->addQuarter(),
            'annual' => $date->addYear(), default => $date->addMonth(),
        };
        $buckets = [];
        foreach ($daily as $date => $count) {
            $key = $start(CarbonImmutable::parse($date))->toDateString();
            $buckets[$key] = ($buckets[$key] ?? 0) + (int) $count;
        }
        $result = collect();
        for ($date = $start($from); $date->lte($to); $date = $next($date)) {
            $end = $next($date)->subDay();
            $result->push([
                'label' => match ($grouping) {
                    'daily' => $date->format('M d'), 'weekly' => 'Week of '.$date->format('M d'),
                    'quarterly' => 'Q'.$date->quarter, 'annual' => $date->format('Y'), default => $date->format('M'),
                },
                'year' => $grouping === 'annual' ? '' : $date->format('Y'),
                'count' => $buckets[$date->toDateString()] ?? 0,
                'from' => $date->max($from)->toDateString(), 'to' => $end->min($to)->toDateString(),
                'partial' => $date->lt($from) || $end->gt($to),
            ]);
        }

        return $result;
    }

    private function peaks(Collection $rows): array
    {
        $maximum = (int) $rows->max('count');
        $peaks = $rows->where('count', $maximum);

        return ['label' => $maximum ? $peaks->pluck('label')->implode(', ') : 'No matching reservations', 'count' => $maximum, 'tied' => $maximum > 0 && $peaks->count() > 1];
    }

    private function seconds(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(CAST(substr($column, 1, 2) AS INTEGER) * 3600 + CAST(substr($column, 4, 2) AS INTEGER) * 60 + CAST(COALESCE(NULLIF(substr($column, 7, 2), ''), '0') AS INTEGER))"
            : "TIME_TO_SEC($column)";
    }

    private function operatingHours(): ?array
    {
        $open = config('gym.reservation.opening_time');
        $close = config('gym.reservation.closing_time');
        if (! is_string($open) || ! is_string($close) || ! preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $open) || ! preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $close) || $close <= $open) {
            return null;
        }
        $seconds = fn ($value) => (int) substr($value, 0, 2) * 3600 + (int) substr($value, 3, 2) * 60;

        return ['open' => $open, 'close' => $close, 'start' => $seconds($open), 'end' => $seconds($close)];
    }

    private function utilization(Builder $query, ?array $operating, CarbonImmutable $from, CarbonImmutable $to, Collection $facilities, array $filters): array
    {
        $unavailable = ['utilizationRate' => null, 'utilizedHours' => null, 'availableHours' => null];
        $facilities = $facilities->when(isset($filters['facility_id']), fn ($rows) => $rows->where('id', $filters['facility_id']));
        if (! $operating || $facilities->isEmpty()) {
            return $unavailable;
        }
        $blocks = ScheduleBlock::whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from)->get();
        $bookings = (clone $query)->where('status', 'approved')->whereColumn('end_time', '>', 'start_time')
            ->get(['facility_id', 'reservation_date', 'start_time', 'end_time'])
            ->groupBy(fn ($row) => $row->facility_id.'|'.$row->reservation_date->toDateString());
        $available = $occupied = 0;
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            if (isset($filters['month']) && $day->month !== (int) $filters['month']) {
                continue;
            }
            foreach ($facilities as $facility) {
                $closed = $blocks->filter(fn ($block) => ($block->facility_id === null || (int) $block->facility_id === (int) $facility->id)
                    && $block->starts_on->toDateString() <= $day->toDateString() && $block->ends_on->toDateString() >= $day->toDateString())
                    ->map(fn ($block) => [$block->start_time ? $this->timeSeconds($block->start_time) : $operating['start'], $block->end_time ? $this->timeSeconds($block->end_time) : $operating['end']])->all();
                $closed[] = [0, $operating['start']];
                $closed[] = [$operating['end'], 86400];
                $windows = $this->subtractIntervals([[$operating['start'], $operating['end']]], $closed);
                $available += $this->intervalDuration($windows);
                $intervals = $bookings->get($facility->id.'|'.$day->toDateString(), collect())
                    ->map(fn ($row) => [$this->timeSeconds($row->start_time), $this->timeSeconds($row->end_time)])->all();
                $occupied += $this->intervalDuration($this->subtractIntervals($intervals, $closed));
            }
        }

        return ['utilizationRate' => $available ? round($occupied / $available * 100, 1) : null,
            'utilizedHours' => round($occupied / 3600, 2), 'availableHours' => round($available / 3600, 2)];
    }

    private function timeSeconds(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));
        return $parts[0] * 3600 + $parts[1] * 60 + ($parts[2] ?? 0);
    }

    private function subtractIntervals(array $intervals, array $closed): array
    {
        foreach ($closed as [$start, $end]) {
            $remaining = [];
            foreach ($intervals as [$left, $right]) {
                if ($end <= $left || $start >= $right) {
                    $remaining[] = [$left, $right];
                } else {
                    if ($left < $start) $remaining[] = [$left, $start];
                    if ($right > $end) $remaining[] = [$end, $right];
                }
            }
            $intervals = $remaining;
        }
        return $intervals;
    }

    private function intervalDuration(array $intervals): int
    {
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        $seconds = 0;
        $previousEnd = 0;
        foreach ($intervals as [$start, $end]) {
            $seconds += max(0, $end - max($start, $previousEnd));
            $previousEnd = max($previousEnd, $end);
        }
        return $seconds;
    }

    private function timeSlots(Builder $query): Collection
    {
        $slots = collect();
        $expressions = [];
        $bindings = [];
        $start = $this->seconds('start_time');
        $end = $this->seconds('end_time');
        for ($hour = 0; $hour < 24; $hour++) {
            $expressions[] = "SUM(CASE WHEN $start < ? AND $end > ? AND end_time > start_time THEN 1 ELSE 0 END) AS slot_$hour";
            array_push($bindings, ($hour + 1) * 3600, $hour * 3600);
        }
        $counts = (clone $query)->selectRaw(implode(', ', $expressions), $bindings)->first();
        for ($hour = 0; $hour < 24; $hour++) {
            $slots->push(['label' => sprintf('%02d:00–%02d:00', $hour, $hour + 1), 'count' => (int) $counts->{'slot_'.$hour}]);
        }

        return $slots;
    }

    private function processing(Builder $query): object
    {
        $ids = (clone $query)->select('id');
        $decisions = DB::table('reservation_status_histories')->selectRaw('reservation_id, created_at AS decided_at')
            ->whereIn('new_status', ['approved', 'rejected', 'cancelled'])->whereIn('reservation_id', $ids);
        foreach (['approved_at', 'rejected_at', 'cancelled_at'] as $column) {
            $decisions->unionAll((clone $query)->selectRaw("id AS reservation_id, $column AS decided_at")->whereNotNull($column)->toBase());
        }
        $first = DB::query()->fromSub($decisions, 'decisions')->selectRaw('reservation_id, MIN(decided_at) AS decided_at')->groupBy('reservation_id');
        $elapsed = DB::connection()->getDriverName() === 'sqlite'
            ? '(julianday(first_decision.decided_at) - julianday(reservations.created_at)) * 24.0'
            : 'TIMESTAMPDIFF(SECOND, reservations.created_at, first_decision.decided_at) / 3600.0';

        return DB::table('reservations')->joinSub($first, 'first_decision', 'reservations.id', '=', 'first_decision.reservation_id')
            ->whereColumn('first_decision.decided_at', '>=', 'reservations.created_at')->selectRaw("AVG($elapsed) AS hours, COUNT(*) AS total")->first();
    }

    private function supplementary(Builder $query): array
    {
        $ids = (clone $query)->select('id');
        $feedback = DB::table('feedback')->whereIn('reservation_id', $ids)->selectRaw('COUNT(*) AS total, AVG(rating) AS average')->first();
        $equipment = DB::table('reservation_equipment')->join('equipment', 'equipment.id', '=', 'reservation_equipment.equipment_id')
            ->whereIn('reservation_id', $ids)->selectRaw('equipment.id, equipment_name, COUNT(*) AS bookings, SUM(quantity_requested) AS requested, SUM(COALESCE(quantity_approved, 0)) AS allocated')
            ->groupBy('equipment.id', 'equipment_name')->orderByDesc('allocated')->orderByDesc('bookings')->limit(5)->get();
        $waiting = DB::table('reservation_status_histories')->whereIn('reservation_id', $ids)->where('new_status', 'waiting_list')
            ->selectRaw('reservation_id, MIN(created_at) AS waiting_at')->groupBy('reservation_id');
        $waitingEver = DB::query()->fromSub($waiting, 'waiting')->count();
        $promoted = DB::table('reservation_status_histories AS promoted')->whereIn('promoted.reservation_id', $ids)
            ->where('promoted.new_status', 'approved')->whereExists(function ($q) {
                $q->selectRaw('1')->from('reservation_status_histories AS waiting')
                    ->whereColumn('waiting.reservation_id', 'promoted.reservation_id')->where('waiting.new_status', 'waiting_list')
                    ->where(function ($q) {
                        $q->whereColumn('promoted.created_at', '>', 'waiting.created_at')
                            ->orWhere(fn ($q) => $q->whereColumn('promoted.created_at', 'waiting.created_at')->whereColumn('promoted.id', '>', 'waiting.id'));
                    });
            })->distinct()->count('promoted.reservation_id');
        // Classify free-text reasons without disclosing their potentially personal contents.
        $reason = "CASE WHEN cancellation_reason IS NULL OR TRIM(cancellation_reason) = '' THEN 'Not recorded' WHEN LOWER(cancellation_reason) LIKE '%weather%' OR LOWER(cancellation_reason) LIKE '%typhoon%' THEN 'Weather' WHEN LOWER(cancellation_reason) LIKE '%conflict%' OR LOWER(cancellation_reason) LIKE '%schedule%' THEN 'Scheduling' WHEN LOWER(cancellation_reason) LIKE '%duplicate%' THEN 'Duplicate request' ELSE 'Other recorded reason' END";
        $reasons = (clone $query)->where('status', 'cancelled')->selectRaw("$reason AS reason_group, COUNT(*) AS total")->groupBy('reason_group')->orderByDesc('total')->get();

        return [
            'feedbackCount' => (int) $feedback->total, 'feedbackAverage' => $feedback->average === null ? null : round((float) $feedback->average, 2),
            'equipmentDemand' => $equipment, 'cancellationReasons' => $reasons,
            'waitingCount' => (clone $query)->where('status', 'waiting_list')->count(), 'waitingEver' => $waitingEver,
            'waitingPromoted' => $promoted, 'waitingConversion' => $waitingEver ? round($promoted / $waitingEver * 100, 1) : null,
            'completionRecorded' => (clone $query)->where('status', 'completed')->whereNotNull('completed_at')->count(),
        ];
    }
}
