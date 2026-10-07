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
use Illuminate\Support\Str;
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
        $facilities = Facility::query()
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
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'setup_time' => ['nullable', 'date_format:H:i'],
            'cleanup_time' => ['nullable', 'date_format:H:i'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['array:quantity_requested'],
            'equipment.*.quantity_requested' => ['nullable', 'integer', 'min:1'],
            'priority_override' => ['nullable', 'boolean'],
        ], ['expected_attendees.max' => \App\Support\GymCapacity::ERROR]);

        app(\App\Services\EquipmentRequestService::class)->validateRequests($validated['reservation_type'], $validated['equipment'] ?? []);

        $conflictingReservations = Reservation::query()
            ->where('facility_id', $validated['facility_id'])
            ->whereDate('reservation_date', $validated['reservation_date'])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where(function ($query) use ($validated): void {
                $query
                    ->where('start_time', '<', $validated['end_time'])
                    ->where('end_time', '>', $validated['start_time']);
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
            if ($validated['priority_override'] ?? false) {
                foreach ($conflictingReservations as $conflict) {
                    $previousStatus = $conflict->status;
                    $conflict->update(['status' => 'waiting_list']);
                    ReservationStatusHistory::create([
                        'reservation_id' => $conflict->id,
                        'changed_by' => $request->user()->id,
                        'previous_status' => $previousStatus,
                        'new_status' => 'waiting_list',
                        'remarks' => 'Schedule reassigned due to an authorized priority reservation.',
                    ]);
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
                'status' => ($validated['priority_override'] ?? false) ? 'approved' : 'new',
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
                'remarks' => ($validated['priority_override'] ?? false) ? 'Authorized priority reservation created and approved.' : 'Reservation created.',
            ]);
            AuditLog::record(($validated['priority_override'] ?? false) ? 'reservation.priority_override' : 'reservation.created', $reservation, [
                'status' => $reservation->status,
                'conflicts_reassigned' => $conflictingReservations->pluck('reference_number')->all(),
            ]);
        });

        if ($validated['priority_override'] ?? false) {
            foreach ($conflictingReservations->whereNotNull('contact_email') as $conflict) {
                try {
                    Mail::raw(
                        "Your MCST Gym reservation {$conflict->reference_number} was moved to the waiting list because an authorized priority reservation requires the selected schedule. Please track your request for further updates: ".route('reservation.track', ['reference' => $conflict->reference_number]),
                        fn ($message) => $message->to($conflict->contact_email)->subject('MCST Gym Reservation Schedule Reassigned')
                    );
                } catch (\Throwable $exception) {
                    Log::error('Priority reassignment notification failed.', ['reservation_id' => $conflict->id, 'exception' => $exception]);
                }
            }
        }

        return redirect()
            ->route('admin.reservations.index')
            ->with('success', 'Reservation created successfully.');
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
        $facilities = Facility::query()
            ->orderBy('facility_name')
            ->get();

        return view('admin.reservations.edit', compact(
            'reservation',
            'facilities'
        ));
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
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
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
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
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
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

        if (! in_array($reservation->status, ['new', 'validated', 'waiting_list'], true)) {
            return back()->withErrors([
                'status' => 'This reservation can no longer be approved.',
            ]);
        }

        DB::transaction(function () use ($request, $reservation, $validated): void {
            $service = app(\App\Services\EquipmentRequestService::class);
            $service->lockInventory();
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (!in_array($reservation->status, ['new', 'validated', 'waiting_list'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'This reservation can no longer be approved.']);
            }
            $previousStatus = $reservation->status;

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
            $previousStatus = $reservation->status;

            $reservation->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['cancellation_reason'],
                'cancelled_by' => $request->user()->id,
                'cancelled_at' => now(),
            ]);

            ReservationStatusHistory::create([
                'reservation_id' => $reservation->id,
                'changed_by' => $request->user()->id,
                'previous_status' => $previousStatus,
                'new_status' => 'cancelled',
                'remarks' => $validated['cancellation_reason'],
            ]);
            AuditLog::record('reservation.cancelled', $reservation, ['previous_status' => $previousStatus, 'reason' => $validated['cancellation_reason']]);
        });

        return back()->with('success', 'Reservation cancelled successfully.');
    }

    public function reschedule(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'reservation_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'force_assign' => ['nullable', 'boolean'],
            'conflict_resolution' => ['required_if:force_assign,1', 'in:waiting_list,cancelled'],
            'reason' => ['required', 'string'],
        ]);
        $conflicts = Reservation::whereKeyNot($reservation->id)->where('facility_id', $validated['facility_id'])
            ->whereDate('reservation_date', $validated['reservation_date'])->whereNotIn('status', ['rejected','cancelled'])
            ->where(fn ($query) => $query->where('start_time','<',$validated['end_time'])->where('end_time','>',$validated['start_time']))->get();
        if ($conflicts->isNotEmpty() && ! ($validated['force_assign'] ?? false)) return back()->withErrors(['reservation_date'=>'The target slot is occupied. Enable force assignment and choose how affected reservations should be handled.']);

        $old = $reservation->only(['facility_id','reservation_date','start_time','end_time']);
        DB::transaction(function () use ($request,$reservation,$validated,$conflicts,$old): void {
            $service = app(\App\Services\EquipmentRequestService::class);
            $service->lockInventory();
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            foreach ($conflicts as $conflict) {
                $previous=$conflict->status; $new=$validated['conflict_resolution'];
                $conflict->update(['status'=>$new,'cancellation_reason'=>$new==='cancelled'?$validated['reason']:$conflict->cancellation_reason,'cancelled_by'=>$new==='cancelled'?$request->user()->id:$conflict->cancelled_by,'cancelled_at'=>$new==='cancelled'?now():$conflict->cancelled_at]);
                ReservationStatusHistory::create(['reservation_id'=>$conflict->id,'changed_by'=>$request->user()->id,'previous_status'=>$previous,'new_status'=>$new,'remarks'=>$validated['reason']]);
            }
            $reservation->update(['facility_id'=>$validated['facility_id'],'reservation_date'=>$validated['reservation_date'],'start_time'=>$validated['start_time'],'end_time'=>$validated['end_time']]);
            $service->assertAllocations($reservation);
            ReservationStatusHistory::create(['reservation_id'=>$reservation->id,'changed_by'=>$request->user()->id,'previous_status'=>$reservation->status,'new_status'=>$reservation->status,'remarks'=>'Reservation rescheduled: '.$validated['reason']]);
            AuditLog::record('reservation.rescheduled',$reservation,['old'=>$old,'new'=>$reservation->only(['facility_id','reservation_date','start_time','end_time']),'affected'=>$conflicts->pluck('reference_number')->all()]);
        });
        foreach ($conflicts->whereNotNull('contact_email')->push($reservation)->whereNotNull('contact_email') as $affected) {
            try { Mail::raw("Reservation {$affected->reference_number} schedule adjustment: {$validated['reason']}\nTrack: ".route('reservation.track',['reference'=>$affected->reference_number]),fn($m)=>$m->to($affected->contact_email)->subject('MCST Gym Schedule Adjustment')); } catch (\Throwable $e) { Log::error('Schedule adjustment email failed',['reservation_id'=>$affected->id]); }
        }
        return back()->with('success','Reservation schedule updated and affected requestors notified.');
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
        do {
            $reference = 'MCST-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (
            Reservation::query()
                ->where('reference_number', $reference)
                ->exists()
        );

        return $reference;
    }
}
