<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StaffMonitoringService
{
    public const STATUSES = ['new', 'pending', 'validated', 'pencil', 'approved', 'rejected', 'waiting_list', 'cancelled', 'completed'];

    public function records(): Builder
    {
        return Reservation::query()->select([
            'id', 'reference_number', 'event_name', 'event_type', 'facility_id',
            'reservation_date', 'start_time', 'end_time', 'setup_time', 'cleanup_time', 'expected_attendees', 'status',
        ])->with('facility:id,facility_name,capacity,status');
    }

    public function onDate(Builder $query, string $date): Builder
    {
        return $query->where('reservation_date', '>=', $date)
            ->where('reservation_date', '<', CarbonImmutable::parse($date)->addDay()->toDateString());
    }

    public function ordered(Builder $query): Builder
    {
        return $query->orderBy('reservation_date')->orderBy('start_time')->orderBy('id');
    }

    public function blocks(string $date): Builder
    {
        return ScheduleBlock::query()->select(['id', 'facility_id', 'starts_on', 'ends_on', 'start_time', 'end_time'])
            ->with('facility:id,facility_name')->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }

    public function facilities(CarbonImmutable $now)
    {
        $date = $now->toDateString();

        return Facility::gym()->select(['id', 'facility_name', 'capacity', 'status'])
            ->withExists([
                'reservations as scheduled_now' => fn ($q) => $this->onDate($q, $date)->where('status', 'approved')
                    ->where('start_time', '<=', $now->format('H:i:s'))->where('end_time', '>', $now->format('H:i:s')),
                'reservations as reserved_today' => fn ($q) => $this->onDate($q, $date)->where('status', 'approved')->where('end_time', '>', $now->format('H:i:s')),
            ])->addSelect(['blocked_now' => DB::table('schedule_blocks')->selectRaw('COUNT(*)')
            ->where(fn ($q) => $q->whereColumn('facility_id', 'facilities.id')->orWhereNull('facility_id'))
            ->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)
            ->where(fn ($q) => $q->whereNull('start_time')->orWhere('start_time', '<=', $now->format('H:i:s')))
            ->where(fn ($q) => $q->whereNull('end_time')->orWhere('end_time', '>', $now->format('H:i:s'))),
            ])->orderBy('facility_name')->get();
    }

    public function filterPeriod(Builder $query, array $filters): Builder
    {
        return $query
            ->when(($filters['year'] ?? 'all') !== 'all', fn ($q) => $q->whereYear('reservation_date', (int) $filters['year']))
            ->when($filters['month'] ?? null, fn ($q, $month) => $q->whereMonth('reservation_date', $month))
            ->when($filters['day'] ?? null, fn ($q, $day) => $q->whereDay('reservation_date', $day));
    }

    public function dashboard(array $filters = ['year' => 'all']): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $today = $now->toDateString();
        $tomorrow = $now->addDay()->toDateString();
        $afterSevenDays = $now->addDays(8)->toDateString();
        $todayQuery = $this->onDate($this->records(), $today)->whereIn('status', ['approved', 'completed']);
        $upcoming = $this->records()->where('reservation_date', '>=', $tomorrow)->where('status', 'approved');
        $statusCounts = $this->filterPeriod(Reservation::query(), $filters)
            ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $statusTotal = (int) $statusCounts->sum();
        $recordedStatuses = Reservation::distinct()->pluck('status');
        $statusBreakdown = collect(self::STATUSES)->merge($recordedStatuses)->unique()->filter(fn ($status) => $recordedStatuses->contains($status))->map(fn ($status) => [
            'status' => $status, 'label' => DashboardAnalyticsService::label($status),
            'count' => (int) $statusCounts->get($status, 0),
            'percent' => $statusTotal ? round((int) $statusCounts->get($status, 0) / $statusTotal * 100, 1) : 0,
            'color' => DashboardAnalyticsService::STATUS_COLORS[$status] ?? '#475569',
        ]);
        $years = Reservation::query()->whereNotNull('reservation_date')->select('reservation_date')->distinct()->get()->toBase()
            ->map(fn ($row) => $row->reservation_date->year)->push($now->year)
            ->when(($filters['year'] ?? 'all') !== 'all', fn ($years) => $years->push((int) $filters['year']))
            ->unique()->sortDesc()->values();
        $equipmentCounts = Equipment::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $facilities = $this->facilities($now);
        $notices = collect();
        foreach ($facilities as $facility) {
            if (in_array($facility->status, ['maintenance', 'unavailable'], true)) {
                $notices->push(['text' => $facility->facility_name.': '.($facility->status === 'maintenance' ? 'under maintenance.' : 'unavailable.'), 'url' => route('staff.schedules.index')]);
            }
        }
        $unavailableEquipment = (int) $equipmentCounts->get('maintenance', 0) + (int) $equipmentCounts->get('unavailable', 0);
        if ($unavailableEquipment) {
            $notices->push(['text' => $unavailableEquipment.' equipment records are under maintenance or unavailable.', 'url' => route('staff.equipment.index')]);
        }
        if ($equipmentCounts->sum() > 0 && (int) $equipmentCounts->get('available', 0) === 0) {
            $notices->push(['text' => 'No equipment records are marked available.', 'url' => route('staff.equipment.index')]);
        }
        $highAttendance = $this->ordered($this->records()->where('status', 'approved')->where('reservation_date', '>=', $today)
            ->where('reservation_date', '<', $afterSevenDays)->where('expected_attendees', '>=', \App\Support\GymCapacity::MAX_ATTENDEES))->limit(5)->get();
        foreach ($highAttendance as $row) {
            $notices->push(['text' => $row->reference_number.': expected attendance ('.$row->expected_attendees.') meets or exceeds gym capacity ('.\App\Support\GymCapacity::MAX_ATTENDEES.').', 'url' => route('staff.reservations.show', $row)]);
        }
        $cancelled = $this->records()->where('status', 'cancelled')->where(function ($q) use ($now) {
            $q->whereBetween('cancelled_at', [$now->subDays(7), $now])->orWhereHas('statusHistories', fn ($q) => $q->where('new_status', 'cancelled')->whereBetween('created_at', [$now->subDays(7), $now]));
        })->orderByDesc('cancelled_at')->orderByDesc('id')->limit(5)->get();
        foreach ($cancelled as $row) {
            $notices->push(['text' => $row->reference_number.': recently cancelled; scheduled date '.$row->reservation_date->toDateString().'.', 'url' => route('staff.reservations.show', $row)]);
        }
        $changed = DB::table('reservation_status_histories AS history')->join('reservations', 'reservations.id', '=', 'history.reservation_id')
            ->where('history.remarks', 'like', 'Reservation rescheduled:%')->whereBetween('history.created_at', [$now->subDays(7), $now])
            ->selectRaw('reservations.id, reservations.reference_number, MAX(history.created_at) AS changed_at')
            ->groupBy('reservations.id', 'reservations.reference_number')->orderByDesc('changed_at')->limit(5)->get();
        foreach ($changed as $row) {
            $notices->push(['text' => $row->reference_number.': schedule changed within the last seven days. Check the current schedule.', 'url' => route('staff.reservations.show', $row->id)]);
        }
        $blockCount = $this->blocks($today)->count();
        if ($blockCount) {
            $notices->push(['text' => $blockCount.' schedule block(s) affect today. Check blocked times before preparing the gym.', 'url' => route('staff.schedules.index', ['date' => $today])]);
        }

        $arQuery = $this->filterPeriod($this->records()->where('status', 'approved'), $filters);
        $pendingArCount = (clone $arQuery)->where(fn ($q) => $q->whereDoesntHave('acknowledgementTask')
            ->orWhereHas('acknowledgementTask', fn ($task) => $task->where('status', '!=', 'completed')))->count();
        $completedArCount = $this->filterPeriod(Reservation::query(), $filters)
            ->whereHas('acknowledgementTask', fn ($task) => $task->where('status', 'completed')->whereNotNull('completed_at'))
            ->whereHas('acknowledgementReceipt')->count();
        $arReservations = app(AcknowledgementReceiptService::class)->orderedTasks((clone $arQuery)->with(['acknowledgementTask', 'acknowledgementReceipt']))->limit(8)->get();

        $gymAvailability = $facilities->isEmpty() || $facilities->every(fn ($f) => $f->status !== 'available' || $f->blocked_now)
            ? 'Unavailable' : ($facilities->contains(fn ($f) => $f->status === 'available' && ! $f->blocked_now && $f->scheduled_now) ? 'Occupied' : 'Available');

        return [
            'now' => $now, 'today' => $today, 'sevenDaysThrough' => $now->addDays(7)->toDateString(),
            'todayCount' => (clone $todayQuery)->count(),
            'upcomingCount' => (clone $upcoming)->where('reservation_date', '<', $afterSevenDays)->count(),
            'todayReservations' => $this->ordered($todayQuery)->paginate(10, ['*'], 'today_page')->appends($filters),
            'upcomingReservations' => $this->ordered($this->filterPeriod($upcoming, $filters))->paginate(5, ['*'], 'upcoming_page')->appends($filters),
            'facilities' => $facilities, 'equipmentCounts' => $equipmentCounts, 'notices' => $notices,
            'filters' => $filters, 'years' => $years, 'statusBreakdown' => $statusBreakdown, 'statusTotal' => $statusTotal,
            'equipmentAttention' => (int) $equipmentCounts->reject(fn ($count, $status) => $status === 'available')->sum(),
            'pendingArCount' => $pendingArCount, 'completedArCount' => $completedArCount,
            'approvedPeriodCount' => (int) $statusCounts->get('approved', 0),
            'arReservations' => $arReservations, 'gymAvailability' => $gymAvailability,
        ];
    }
}
