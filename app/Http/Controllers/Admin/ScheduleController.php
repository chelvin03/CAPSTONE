<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use App\Services\AdminScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request, AdminScheduleService $schedule): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in([...config('gym.statuses'), 'pending', 'pencil'])],
            'event_type' => ['nullable', 'string', 'max:100'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
        ]);
        $filters['facility_id'] = Facility::gym()->value('id');
        $month = $filters['month'] ?? now()->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        $end = $start->endOfMonth();
        $selectedDate = $filters['date'] ?? ($month === now()->format('Y-m') ? now()->toDateString() : $start->toDateString());
        if (substr($selectedDate, 0, 7) !== $month) {
            $selectedDate = $start->toDateString();
        }
        $facilities = Facility::gym()->orderBy('facility_name')->get(['id', 'facility_name', 'status']);
        $reservableFacilities = $facilities->where('status', 'available')->values();
        $scope = isset($filters['facility_id']) ? $facilities->where('id', $filters['facility_id'])->values() : $facilities;
        $reservations = Reservation::with('facility:id,facility_name')->whereDate('reservation_date', '>=', $start->toDateString())->whereDate('reservation_date', '<=', $end->toDateString())
            ->when(isset($filters['facility_id']), fn ($query) => $query->where('facility_id', $filters['facility_id']))
            ->orderBy('reservation_date')->orderBy('start_time')->orderBy('id')
            ->get(['id', 'reference_number', 'facility_id', 'event_name', 'event_type', 'reservation_date', 'start_time', 'end_time', 'setup_time', 'cleanup_time', 'status']);
        $blocks = ScheduleBlock::with('facility:id,facility_name')->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)
            ->when(isset($filters['facility_id']), fn ($query) => $query->where(fn ($scope) => $scope->whereNull('facility_id')->orWhere('facility_id', $filters['facility_id'])))
            ->orderBy('starts_on')->get();
        $tableReservations = $reservations
            ->when(! empty($filters['status']), fn ($rows) => $rows->where('status', $filters['status']))
            ->when(! empty($filters['event_type']), fn ($rows) => $rows->where('event_type', $filters['event_type']));
        $eventTypes = Reservation::whereNotNull('event_type')->where('event_type', '!=', '')->distinct()->orderBy('event_type')->pluck('event_type');
        $navigationFilters = array_filter($filters, fn ($value, $key) => ! in_array($key, ['month', 'date'], true) && $value !== null && $value !== '', ARRAY_FILTER_USE_BOTH);

        return view('admin.schedule.index', [
            ...$schedule->month($start, $scope, $reservations, $blocks),
            'maximumBlockDays' => max(1, min(7, (int) \App\Models\SystemSetting::getValue('maximum_block_days', 7))),
            'month' => $month, 'monthLabel' => $start->format('F Y'), 'selectedDate' => $selectedDate,
            'leadingDays' => $start->dayOfWeek, 'filters' => $filters, 'navigationFilters' => $navigationFilters,
            'previousMonth' => $start->subMonth()->format('Y-m'), 'nextMonth' => $start->addMonth()->format('Y-m'),
            'reservations' => $tableReservations, 'blocks' => $blocks, 'facilities' => $facilities,
            'reservableFacilities' => $reservableFacilities, 'eventTypes' => $eventTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->blockDetails($request);
        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $request): void {
            Facility::whereKey($validated['facility_id'])->lockForUpdate()->firstOrFail();
            $impact = app(\App\Services\ScheduleImpactService::class);
            $records = $impact->blockBookings($validated, true);
            if ($records->isNotEmpty() || $request->filled('impact_token')) $impact->confirm($request->input('impact_token'), 'block', $validated, $records);
            $block = ScheduleBlock::create([...$validated, 'created_by' => $request->user()->id]);
            AuditLog::record('schedule_block.created', $block, $validated);
            $affected = $records->where('status', 'approved');
            $reason = 'Administrator schedule block: '.$block->title;
            if (filled($block->reason)) {
                $reason .= '. '.$block->reason;
            }
            foreach ($affected as $reservation) {
                app(\App\Services\ReservationCancellationService::class)->cancel($reservation, $request->user()->id, $reason);
            }
        });

        return back()->with('success', 'Blackout period created. Conflicting approved reservations were cancelled and requestors notified by email.');
    }

    public function preview(Request $request): \Illuminate\Http\JsonResponse
    {
        $details = $this->blockDetails($request);
        $impact = app(\App\Services\ScheduleImpactService::class);
        $records = $impact->blockBookings($details);
        $rows = $records->map(function ($booking) use ($impact) {
            $cancel = $booking->status === 'approved';
            $row = $impact->row($booking, $cancel ? 'Cancel reservation and email the reason with rescheduling instructions.'
                : ($booking->status === 'completed' ? 'Completed record stays unchanged; review this historical overlap.' : 'Request stays pending/unconfirmed but cannot be approved in this blocked interval. Reschedule it separately.'));
            if (! $cancel) $row['email'] = 'No cancellation email; status remains unchanged.';
            return $row;
        });
        return response()->json(['summary' => $records->where('status', 'approved')->count().' approved bookings will be cancelled. Other overlapping requests remain unchanged.',
            'reason' => $details['reason'] ?? $details['title'], 'rows' => $rows->values(),
            'token' => $impact->token('block', $details, $records)])->header('Cache-Control', 'no-store, private');
    }

    private function blockDetails(Request $request): array
    {
        $request->merge(['facility_id' => Facility::gymId()]);
        $validated = $request->validate([
            'impact_token' => ['nullable', 'string'],
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_with:start_time', 'date_format:H:i', 'after:start_time'],
        ]);
        $maximumBlockDays = max(1, min(7, (int) \App\Models\SystemSetting::getValue('maximum_block_days', 7)));
        if (CarbonImmutable::parse($validated['ends_on'])->greaterThan(CarbonImmutable::parse($validated['starts_on'])->addDays($maximumBlockDays - 1))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ends_on' => 'Schedule blocks can cover a maximum of '.$maximumBlockDays.' days, including the start and end dates.',
            ]);
        }
        unset($validated['impact_token']);
        return $validated;
    }
    public function destroy(ScheduleBlock $block): RedirectResponse
    {
        AuditLog::record('schedule_block.released', $block, ['title' => $block->title]);
        $block->delete();

        return back()->with('success', 'Date or slot reopened.');
    }
}
