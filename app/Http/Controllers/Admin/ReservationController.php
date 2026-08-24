<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();

        $reservations = Reservation::query()
            ->with(['user', 'facility', 'equipment'])
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

        return view('admin.reservations.create', compact(
            'facilities',
            'equipment'
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
            'expected_attendees' => ['required', 'integer', 'min:1'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'setup_time' => ['nullable', 'date_format:H:i'],
            'cleanup_time' => ['nullable', 'date_format:H:i'],
            'equipment' => ['nullable', 'array'],
            'equipment.*.quantity_requested' => ['nullable', 'integer', 'min:1'],
        ]);

        $hasConflict = Reservation::query()
            ->where('facility_id', $validated['facility_id'])
            ->where('reservation_date', $validated['reservation_date'])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where(function ($query) use ($validated): void {
                $query
                    ->where('start_time', '<', $validated['end_time'])
                    ->where('end_time', '>', $validated['start_time']);
            })
            ->exists();

        if ($hasConflict) {
            return back()
                ->withInput()
                ->withErrors([
                    'reservation_date' => 'The selected facility already has a conflicting reservation.',
                ]);
        }

        DB::transaction(function () use ($request, $validated): void {
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
                'expected_attendees' => $validated['expected_attendees'],
                'reservation_date' => $validated['reservation_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'setup_time' => $validated['setup_time'] ?? null,
                'cleanup_time' => $validated['cleanup_time'] ?? null,
                'status' => 'new',
            ]);

            $equipmentData = [];

            foreach ($validated['equipment'] ?? [] as $equipmentId => $item) {
                $quantity = $item['quantity_requested'] ?? null;

                if ($quantity) {
                    $equipmentData[$equipmentId] = [
                        'quantity_requested' => $quantity,
                        'quantity_approved' => null,
                        'remarks' => null,
                    ];
                }
            }

            if ($equipmentData !== []) {
                $reservation->equipment()->sync($equipmentData);
            }

            ReservationStatusHistory::create([
                'reservation_id' => $reservation->id,
                'changed_by' => $request->user()->id,
                'previous_status' => null,
                'new_status' => 'new',
                'remarks' => 'Reservation created.',
            ]);
        });

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
        ]);

        return view('admin.reservations.show', compact('reservation'));
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
                    $reservation->equipment()->updateExistingPivot(
                        $equipmentId,
                        [
                            'quantity_approved' => $item['quantity_approved'] ?? 0,
                        ]
                    );
                }
            }

            ReservationStatusHistory::create([
                'reservation_id' => $reservation->id,
                'changed_by' => $request->user()->id,
                'previous_status' => $previousStatus,
                'new_status' => 'approved',
                'remarks' => $validated['admin_notes'] ?? 'Reservation approved.',
            ]);
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
        });

        return back()->with('success', 'Reservation cancelled successfully.');
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
