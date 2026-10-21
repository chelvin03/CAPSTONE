<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $dashboardFilters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'category' => ['nullable', 'string', 'max:100'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'requestor_type' => ['nullable', 'string', 'max:50'],
        ]);

        $reservations = Reservation::query()
            ->with(['user', 'facility', 'equipment'])
            ->when($dashboardFilters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('reservation_date', '>=', $date))
            ->when($dashboardFilters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('reservation_date', '<=', $date))
            ->when($dashboardFilters['month'] ?? null, fn ($q, $month) => $q->whereMonth('reservation_date', $month))
            ->when($dashboardFilters['category'] ?? null, fn ($q, $type) => $q->where('event_type', $type))
            ->when($dashboardFilters['facility_id'] ?? null, fn ($q, $id) => $q->where('facility_id', $id))
            ->when($dashboardFilters['requestor_type'] ?? null, fn ($q, $type) => $q->where('reservation_type', $type))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('event_name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhereHas('facility', function ($facilityQuery) use ($search): void {
                            $facilityQuery->where('facility_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.reservations.index', compact(
            'reservations',
            'search',
            'status'
        ));
    }

    public function create(): View
    {
        $facilities = Facility::gym()
            ->where('status', 'available')
            ->orderBy('facility_name')
            ->get();

        $equipment = Equipment::query()
            ->where('status', 'available')
            ->orderBy('equipment_name')
            ->get();

        $minimumNoticeDays = max(0, (int)
            \App\Models\SystemSetting::getValue('minimum_notice_days', config('gym.reservation.minimum_notice_days', 3))
        );

        return view('admin.reservations.create', compact(
            'facilities',
            'equipment',
            'minimumNoticeDays'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['facility_id' => Facility::gymId()]);
        $validated = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'reservation_type' => ['required', 'string', 'max:50'],
            'event_name' => ['required', 'string', 'max:255'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'purpose' => ['required', 'string'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'expected_attendees' => \App\Support\GymCapacity::rules(),
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i', new \App\Rules\HalfHourTime],
            'end_time' => ['required', new \App\Rules\HalfHourTime, new \App\Rules\MinimumReservationDuration, 'date_format:H:i', 'after:start_time'],
            'setup_time' => ['nullable', 'date_format:H:i'],
            'cleanup_time' => ['nullable', 'date_format:H:i'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['array:quantity_requested'],
            'equipment.*.quantity_requested' => ['nullable', 'integer', 'min:1'],
            'priority_override' => ['nullable', 'boolean'],
            'pencil_booking' => ['nullable', 'boolean'],
        ], ['expected_attendees.max' => \App\Support\GymCapacity::ERROR]);

        if (($validated['pencil_booking'] ?? false) && ($validated['priority_override'] ?? false)) {
            return back()->withInput()->withErrors(['pencil_booking' => 'A pencil booking cannot use priority override.']);
        }

        app(\App\Services\EquipmentRequestService::class)->validateRequests($validated['reservation_type'], $validated['equipment'] ?? []);

        $conflictingReservations = Reservation::query()
            ->where('facility_id', $validated['facility_id'])
            ->whereDate('reservation_date', $validated['reservation_date'])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where(function ($query) use ($validated): void {
                $query
                    ->where('start_time', '<', app(\App\Services\PublicCalendarService::class)->bufferedTime($validated['end_time'], 30))
                    ->where('end_time', '>', app(\App\Services\PublicCalendarService::class)->bufferedTime($validated['start_time'], -30));
            })
            ->get();

        if ($conflictingReservations->isNotEmpty() && ! ($validated['priority_override'] ?? false)) {
            return back()
                ->withInput()
                ->withErrors([
                    'reservation_date' => 'The selected facility already has a conflicting reservation.',
                ]);
        }

        DB::transaction(function () use ($request, $validated, $conflictingReservations): void {
            app(\App\Services\EquipmentRequestService::class)->lockInventory();
            if ($validated['pencil_booking'] ?? false) {
                $this->assertPencilSchedule($validated);
            }
            if ($validated['priority_override'] ?? false) {
                foreach ($conflictingReservations as $conflict) {
                    app(\App\Services\ReservationCancellationService::class)->cancel(
                        $conflict, $request->user()->id,
                        'An authorized priority reservation requires this schedule.'
                    );
                }
            }

            $reservation = Reservation::create([
                'reference_number' => $this->generateReferenceNumber(),
                'user_id' => $request->user()->id,
                'facility_id' => $validated['facility_id'],
                'reservation_type' => $validated['reservation_type'],
                'event_name' => $validated['event_name'],
                'event_type' => $validated['event_type'] ?? null,
                'purpose' => $validated['purpose'],
                'contact_person' => $validated['contact_person'],
                'contact_number' => $validated['contact_number'],
                'contact_email' => $validated['contact_email'] ?? null,
                'expected_attendees' => $validated['expected_attendees'],
                'reservation_date' => $validated['reservation_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                    'setup_time' => $validated['setup_time'] ?? null,
                    'cleanup_time' => $validated['cleanup_time'] ?? null,
                'status' => ($validated['pencil_booking'] ?? false) ? 'pencil' : (($validated['priority_override'] ?? false) ? 'approved' : 'new'),
                'priority_number' => ($validated['priority_override'] ?? false) ? 1 : null,
                'approved_by' => ($validated['priority_override'] ?? false) ? $request->user()->id : null,
                'approved_at' => ($validated['priority_override'] ?? false) ? now() : null,
            ]);

            app(\App\Services\EquipmentRequestService::class)->attach($reservation, $validated['equipment'] ?? []);

            ReservationStatusHistory::create([
                'reservation_id' => $reservation->id,
                'changed_by' => $request->user()->id,
                'previous_status' => null,
                'new_status' => $reservation->status,
                'remarks' => ($validated['pencil_booking'] ?? false) ? 'Pencil booking created. Schedule held until confirmed or cancelled by an administrator.' : (($validated['priority_override'] ?? false) ? 'Authorized priority reservation created and approved.' : 'Reservation created.'),
            ]);
            AuditLog::record(($validated['priority_override'] ?? false) ? 'reservation.priority_override' : 'reservation.created', $reservation, [
                'status' => $reservation->status,
                'conflicts_cancelled' => $conflictingReservations->pluck('reference_number')->all(),
            ]);
        });

        return redirect()
            ->route('admin.reservations.index')
            ->with('success', ($validated['pencil_booking'] ?? false) ? 'Pencil booking created. The schedule is held until you confirm or cancel it.' : 'Reservation created successfully.');
    }

    public function show(Reservation $reservation): View
    {
        $reservation->load([
            'user',
            'facility',
            'equipment',
            'documents',
            'statusHistories.changedBy',
            'approvedBy',
            'rejectedBy',
            'cancelledBy',
            'notifications',
        ]);

        return view('admin.reservations.show', compact('reservation'));
    }

    public function edit(Reservation $reservation): View
    {
        $facilities = Facility::gym()
            ->orderBy('facility_name')
            ->get();

        return view('admin.reservations.edit', compact(
            'reservation',
            'facilities'
        ));
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $request->merge(['facility_id' => Facility::gymId()]);
        $validated = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'reservation_type' => ['required', 'string', 'max:50'],
            'event_name' => ['required', 'string', 'max:255'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'purpose' => ['required', 'string'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'digits:11'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'expected_attendees' => \App\Support\GymCapacity::rules(),
            'reservation_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i', new \App\Rules\HalfHourTime],
            'end_time' => ['required', new \App\Rules\HalfHourTime, new \App\Rules\MinimumReservationDuration, 'date_format:H:i', 'after:start_time'],
            'setup_time' => ['nullable', 'date_format:H:i'],
            'cleanup_time' => ['nullable', 'date_format:H:i'],
            'requested_equipment' => ['nullable', 'string', 'max:255', 'required_with:requested_equipment_quantity'],
            'requested_equipment_quantity' => ['nullable', 'integer', 'min:1', 'required_with:requested_equipment'],
        ], ['expected_attendees.max' => \App\Support\GymCapacity::ERROR]);

        app(\App\Services\EquipmentRequestService::class)->validateRequests($validated['reservation_type'],
            $reservation->equipment()->get()->mapWithKeys(fn ($item) => [$item->id => ['quantity_requested' => $item->pivot->quantity_requested]])->all(),
            $validated['requested_equipment'] ?? null, false);

        $hasConflict = Reservation::query()
            ->whereKeyNot($reservation->id)
            ->where('facility_id', $validated['facility_id'])
            ->whereDate('reservation_date', $validated['reservation_date'])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where('start_time', '<', app(\App\Services\PublicCalendarService::class)->bufferedTime($validated['end_time'], 30))
            ->where('end_time', '>', app(\App\Services\PublicCalendarService::class)->bufferedTime($validated['start_time'], -30))
            ->exists();

        if ($hasConflict) {
            return back()->withInput()->withErrors([
                'reservation_date' => 'The selected facility already has a conflicting reservation during this time.',
            ]);
        }

        DB::transaction(function () use ($reservation, $validated): void {
            $service = app(\App\Services\EquipmentRequestService::class);
            $service->lockInventory();
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $reservation->update($validated);
            $service->assertAllocations($reservation);
        });

        AuditLog::record('reservation.updated', $reservation, [
            'reference_number' => $reservation->reference_number,
        ]);

        return redirect()
            ->route('admin.reservations.index')
            ->with('success', 'Reservation updated successfully.');
    }

    public function approve(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string'],
            'equipment' => ['nullable', 'array'],
            'equipment.*.quantity_approved' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! in_array($reservation->status, ['new', 'validated', 'waiting_list', 'pencil'], true)) {
            return back()->withErrors([
                'status' => 'This reservation can no longer be approved.',
            ]);
        }

        DB::transaction(function () use ($request, $reservation, $validated): void {
            $service = app(\App\Services\EquipmentRequestService::class);
            $service->lockInventory();
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (!in_array($reservation->status, ['new', 'validated', 'waiting_list', 'pencil'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'This reservation can no longer be approved.']);
            }
            $previousStatus = $reservation->status;

            if ($previousStatus === 'pencil') {
                $this->assertPencilSchedule([
                    'facility_id' => $reservation->facility_id,
                    'reservation_date' => $reservation->reservation_date->toDateString(),
                    'start_time' => substr($reservation->start_time, 0, 5),
                    'end_time' => substr($reservation->end_time, 0, 5),
                ], $reservation->id);
            }

            $reservation->update([
                'status' => 'approved',
                'admin_notes' => $validated['admin_notes'] ?? null,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            foreach ($validated['equipment'] ?? [] as $equipmentId => $item) {
                if ($reservation->equipment->contains('id', (int) $equipmentId)) {
                    $equipment = Equipment::findOrFail($equipmentId);
                    $requestedItem = $reservation->equipment->firstWhere('id', (int) $equipmentId);
                    $quantity = (int) ($item['quantity_approved'] ?? 0);
                    if ($quantity > 0) {
                        $service->approveQuantity($reservation, $equipment, $quantity);
                    } else {
                        $reservation->equipment()->updateExistingPivot($equipmentId, ['quantity_approved' => 0, 'status' => 'rejected', 'requires_response' => false, 'latest_offer_id' => null]);
                    }
                    $service->publish($reservation, ['equipment_id' => $equipment->id, 'equipment_name' => $equipment->equipment_name,
                        'requested' => (int) $requestedItem->pivot->quantity_requested, 'available' => $service->available($equipment, $reservation),
                        'quantity' => $quantity, 'message' => $quantity > 0 ? 'Your equipment request has been approved.' : 'Your equipment request was rejected.',
                        'action' => $quantity > 0 ? 'approve' : 'reject']);
                }
            }

            $service->assertAllocations($reservation);

            ReservationStatusHistory::create([
                'reservation_id' => $reservation->id,
                'changed_by' => $request->user()->id,
                'previous_status' => $previousStatus,
                'new_status' => 'approved',
                'remarks' => $validated['admin_notes'] ?? 'Reservation approved.',
            ]);
            AuditLog::record('reservation.approved', $reservation, ['previous_status' => $previousStatus]);
        });

        return back()->with('success', 'Reservation approved successfully.');
    }

    public function reject(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string'],
        ]);

        if (in_array($reservation->status, ['approved', 'cancelled', 'completed'], true)) {
            return back()->withErrors([
                'status' => 'This reservation can no longer be rejected.',
            ]);
        }

        DB::transaction(function () use ($request, $reservation, $validated): void {
            $previousStatus = $reservation->status;

            $reservation->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'rejected_by' => $request->user()->id,
                'rejected_at' => now(),
            ]);

            ReservationStatusHistory::create([
                'reservation_id' => $reservation->id,
                'changed_by' => $request->user()->id,
                'previous_status' => $previousStatus,
                'new_status' => 'rejected',
                'remarks' => $validated['rejection_reason'],
            ]);
            AuditLog::record('reservation.rejected', $reservation, ['previous_status' => $previousStatus, 'reason' => $validated['rejection_reason']]);
        });

        return back()->with('success', 'Reservation rejected successfully.');
    }

    private function assertPencilSchedule(array $details, ?int $except = null): void
    {
        $gym = Facility::whereKey($details['facility_id'])->lockForUpdate()->firstOrFail();
        $conflict = Reservation::where('facility_id', $gym->id)
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->whereDate('reservation_date', $details['reservation_date'])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where('start_time', '<', app(\App\Services\PublicCalendarService::class)->bufferedTime($details['end_time'], 30))
            ->where('end_time', '>', app(\App\Services\PublicCalendarService::class)->bufferedTime($details['start_time'], -30))->exists();
        $blocked = \App\Models\ScheduleBlock::whereDate('starts_on', '<=', $details['reservation_date'])
            ->whereDate('ends_on', '>=', $details['reservation_date'])
            ->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $gym->id))
            ->where(fn ($q) => $q->whereNull('start_time')->orWhere('start_time', '<', $details['end_time']))
            ->where(fn ($q) => $q->whereNull('end_time')->orWhere('end_time', '>', $details['start_time']))->exists();
        if ($gym->status !== 'available' || $conflict || $blocked || $details['reservation_date'] < now()->toDateString()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['reservation_date' => 'This schedule is unavailable for a pencil booking. Choose another date or time.']);
        }
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string'],
        ]);

        if (in_array($reservation->status, ['cancelled', 'completed', 'rejected'], true)) {
            return back()->withErrors([
                'status' => 'This reservation can no longer be cancelled.',
            ]);
        }

        DB::transaction(function () use ($request, $reservation, $validated): void {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            app(\App\Services\ReservationCancellationService::class)->cancel($locked, $request->user()->id, $validated['cancellation_reason']);
        });
        return back()->with('success', 'Reservation cancelled successfully.');
    }

    public function reschedule(Request $request, Reservation $reservation): RedirectResponse
    {
        $request->merge(['facility_id' => Facility::gymId()]);
        $validated = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'reservation_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i', new \App\Rules\HalfHourTime],
            'adjust_duration' => ['nullable', 'boolean'],
            'end_time' => ['exclude_unless:adjust_duration,1', 'required', new \App\Rules\HalfHourTime, new \App\Rules\MinimumReservationDuration, function ($attribute, $value, $fail) use ($request) {
                if (! is_string($value) || $value <= (string) $request->input('start_time')) {
                    $fail('End time must be after start time. Choose 12:00 midnight for the end of the selected date.');
                }
            }],
            'force_assign' => ['nullable', 'boolean'],
            'conflict_resolution' => ['required_if:force_assign,1', 'in:waiting_list,cancelled'],
            'reason' => ['required', 'string'],
        ]);
        DB::transaction(function () use ($request, $reservation, $validated): void {
            $service = app(\App\Services\EquipmentRequestService::class);
            $service->lockInventory();
            $facility = Facility::whereKey($validated['facility_id'])->lockForUpdate()->firstOrFail();
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (in_array($reservation->status, ['cancelled', 'rejected', 'completed'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['reservation_date' => 'Only active reservations may be rescheduled.']);
            }
            $minutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
            $originalDuration = $minutes($reservation->end_time) - $minutes($reservation->start_time);
            if (! ($validated['adjust_duration'] ?? false)) {
                $end = $minutes($validated['start_time']) + $originalDuration;
                if ($originalDuration < 60 || $end > $minutes(config('gym.reservation.closing_time', '24:00'))) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'end_time' => 'The original duration does not fit before closing. Choose an earlier start time or deliberately enable duration adjustment.',
                    ]);
                }
                $validated['end_time'] = sprintf('%02d:%02d', intdiv($end, 60), $end % 60);
            }
            if ($facility->status !== 'available' || $validated['reservation_date'] < now()->toDateString()
                || $validated['start_time'] < config('gym.reservation.opening_time', '07:00')
                || $validated['end_time'] > config('gym.reservation.closing_time', '24:00')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['reservation_date' => 'Choose a future available schedule within gym operating hours.']);
            }
            $blocked = \App\Models\ScheduleBlock::whereDate('starts_on', '<=', $validated['reservation_date'])
                ->whereDate('ends_on', '>=', $validated['reservation_date'])
                ->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $facility->id))
                ->where(fn ($q) => $q->whereNull('start_time')->orWhere(fn ($t) => $t->where('start_time', '<', $validated['end_time'])->where('end_time', '>', $validated['start_time'])))->exists();
            if ($blocked) throw \Illuminate\Validation\ValidationException::withMessages(['reservation_date' => 'Release the administrator schedule block before assigning this time.']);
            $calendar = app(\App\Services\PublicCalendarService::class);
            $conflicts = Reservation::whereKeyNot($reservation->id)->where('facility_id', $facility->id)
                ->whereDate('reservation_date', $validated['reservation_date'])->whereNotIn('status', ['rejected', 'cancelled'])
                ->where('start_time', '<', $calendar->bufferedTime($validated['end_time'], 30))
                ->where('end_time', '>', $calendar->bufferedTime($validated['start_time'], -30))->lockForUpdate()->get();
            if ($conflicts->isNotEmpty() && ! ($validated['force_assign'] ?? false)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['reservation_date' => 'The target slot is occupied. Enable force assignment and choose a conflict resolution.']);
            }
            if ($conflicts->contains('status', 'completed')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['reservation_date' => 'Completed reservations cannot be displaced.']);
            }
            $old = $reservation->only(['facility_id', 'reservation_date', 'start_time', 'end_time']);
            foreach ($conflicts as $conflict) {
                if ($validated['conflict_resolution'] === 'cancelled') {
                    app(\App\Services\ReservationCancellationService::class)->cancel($conflict, $request->user()->id, $validated['reason']);
                } else {
                    $previous = $conflict->status;
                    $before = $conflict->only(['reservation_date', 'start_time', 'end_time']);
                    $conflict->update(['status' => 'waiting_list']);
                    ReservationStatusHistory::create(['reservation_id' => $conflict->id, 'changed_by' => $request->user()->id,
                        'previous_status' => $previous, 'new_status' => 'waiting_list', 'remarks' => $validated['reason']]);
                    AuditLog::record('reservation.waitlisted', $conflict, ['previous_status' => $previous, 'reason' => $validated['reason']]);
                    $this->sendScheduleNotice($conflict, $validated['reason'], $before, 'waiting_list');
                }
            }
            $reservation->update(['facility_id' => $facility->id, 'reservation_date' => $validated['reservation_date'],
                'start_time' => $validated['start_time'], 'end_time' => $validated['end_time']]);
            $service->assertAllocations($reservation);
            ReservationStatusHistory::create(['reservation_id' => $reservation->id, 'changed_by' => $request->user()->id,
                'previous_status' => $reservation->status, 'new_status' => $reservation->status,
                'remarks' => 'Reservation rescheduled: '.$validated['reason']]);
            AuditLog::record('reservation.rescheduled', $reservation, ['old' => $old,
                'new' => $reservation->only(['facility_id', 'reservation_date', 'start_time', 'end_time']),
                'reason' => $validated['reason'], 'affected' => $conflicts->pluck('reference_number')->all()]);
            $this->sendScheduleNotice($reservation, $validated['reason'], $old, 'rescheduled');
        });
        return back()->with('success', 'Schedule updated. Email notices were requested for affected requestors with an email address.');
    }

    private function sendScheduleNotice(Reservation $reservation, string $reason, array $old, string $outcome): void
    {
        DB::afterCommit(function () use ($reservation, $reason, $old, $outcome): void {
            if (! $reservation->contact_email) return;
            try {
                Mail::to($reservation->contact_email)->send(new \App\Mail\ReservationScheduleChanged($reservation, $reason, $old, $outcome));
            } catch (\Throwable $exception) {
                Log::error('Schedule notification failed.', ['reservation_id' => $reservation->id, 'outcome' => $outcome, 'exception_type' => get_class($exception)]);
            }
        });
    }
    public function staffIndex(Request $request): View
    {
        $search = trim((string) $request->input('search'));

        $reservations = Reservation::query()
            ->with(['facility', 'equipment'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('event_name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view(
            'staff.reservation.index',
            compact('reservations', 'search')
        );
    }

    public function staffShow(Reservation $reservation): View
    {
        $reservation->load([
            'facility',
            'equipment',
            'statusHistories',
        ]);

        return view(
            'staff.reservation.show',
            compact('reservation')
        );
    }

    private function generateReferenceNumber(): string
    {
        return app(\App\Services\ReservationReferenceService::class)->next();
    }
}
