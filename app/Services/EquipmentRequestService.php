<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Notifications\EquipmentUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class EquipmentRequestService
{
    public function lockInventory(): void
    {
        // One consistent lock order for equipment reviews and reservation schedule changes.
        Equipment::orderBy('id')->lockForUpdate()->get();
    }

    public function available(Equipment $equipment, Reservation $reservation): int
    {
        if ($equipment->status !== 'available') return 0;
        $rows = DB::table('reservation_equipment as re')->join('reservations as r', 'r.id', '=', 're.reservation_id')
            ->where('re.equipment_id', $equipment->id)->where('r.id', '!=', $reservation->id ?? 0)
            ->whereIn('r.status', ['approved', 'completed'])->whereDate('r.reservation_date', $reservation->reservation_date)
            ->where('r.start_time', '<', $reservation->end_time)->where('r.end_time', '>', $reservation->start_time)
            ->where('re.quantity_approved', '>', 0)->get(['r.start_time', 'r.end_time', 're.quantity_approved']);
        // Peak simultaneous allocation, rather than summing disjoint bookings.
        $events = [];
        foreach ($rows as $row) {
            $start = max(substr($row->start_time, 0, 5), substr($reservation->start_time, 0, 5));
            $end = min(substr($row->end_time, 0, 5), substr($reservation->end_time, 0, 5));
            $events[$start] = ($events[$start] ?? 0) + (int) $row->quantity_approved;
            $events[$end] = ($events[$end] ?? 0) - (int) $row->quantity_approved;
        }
        ksort($events);
        $used = $peak = 0;
        foreach ($events as $delta) { $used += $delta; $peak = max($peak, $used); }
        return max(0, $equipment->total_quantity - $peak);
    }

    public function validateRequests(string $type, array $requests, ?string $custom = null, bool $checkInventory = true): void
    {
        $requested = array_filter($requests, fn ($row) => ($row['quantity_requested'] ?? null) !== null && ($row['quantity_requested'] ?? '') !== '');
        if ($type !== 'internal' && ($requested || filled($custom))) {
            throw ValidationException::withMessages(['equipment' => 'Only Internal MCST requestors may request MCST equipment.']);
        }
        foreach ($checkInventory ? $requested : [] as $id => $row) {
            if (!ctype_digit((string) $id) || !Equipment::whereKey($id)->where('status', 'available')->exists()) {
                throw ValidationException::withMessages(['equipment' => 'Please select equipment from the available inventory.']);
            }
        }
    }

    public function attach(Reservation $reservation, array $requests): void
    {
        foreach ($requests as $id => $row) {
            if (filled($row['quantity_requested'] ?? null)) {
                $reservation->equipment()->attach($id, ['quantity_requested' => $row['quantity_requested'], 'status' => 'pending']);
                AuditLog::record('equipment.requested', $reservation, ['equipment_id' => $id, 'quantity_requested' => $row['quantity_requested']]);
            }
        }
    }

    public function assertAllocations(Reservation $reservation): void
    {
        foreach ($reservation->equipment()->get() as $equipment) {
            if ((int) $equipment->pivot->quantity_approved > 0) {
                if ($reservation->reservation_type !== 'internal') {
                    throw ValidationException::withMessages(['equipment' => 'Only Internal MCST requestors may receive MCST equipment.']);
                }
                if ($equipment->pivot->quantity_approved > $this->available($equipment, $reservation)) {
                    throw ValidationException::withMessages(['equipment' => $equipment->equipment_name.' has insufficient stock for this reservation schedule.']);
                }
            }
        }
    }

    public function approveQuantity(Reservation $reservation, Equipment $equipment, int $quantity): void
    {
        $item = $reservation->equipment()->where('equipment.id', $equipment->id)->firstOrFail();
        if ($reservation->reservation_type !== 'internal' || in_array($reservation->status, ['cancelled', 'rejected', 'completed'], true)) {
            throw ValidationException::withMessages(['equipment' => 'This reservation cannot receive equipment approvals.']);
        }
        if ($quantity < 1 || $quantity > $item->pivot->quantity_requested || $quantity > $this->available($equipment, $reservation)) {
            throw ValidationException::withMessages(['quantity_approved' => 'Approved quantity must be positive and cannot exceed the requested or available quantity.']);
        }
        if ($item->pivot->requires_response && ($item->pivot->requestor_response !== 'accepted' || $quantity > $item->pivot->quantity_offered)) {
            throw ValidationException::withMessages(['equipment' => 'Wait for the requestor to accept the offered quantity before final approval.']);
        }
        $reservation->equipment()->updateExistingPivot($equipment->id, ['quantity_approved' => $quantity, 'status' => 'approved']);
        AuditLog::record('equipment.approved', $reservation, ['equipment_id' => $equipment->id, 'quantity_approved' => $quantity]);
    }

    public function publish(Reservation $reservation, array $details, bool $email = true): void
    {
        $details += ['title' => 'Equipment Request Update', 'reference' => $reservation->reference_number, 'reservation_id' => $reservation->id];
        $reservation->notify(new EquipmentUpdate($details));
        DB::afterCommit(function () use ($reservation, $details, $email): void {
            if (!$email || !$reservation->contact_email) return;
            try {
                $link = URL::temporarySignedRoute('reservation.equipment.access', now()->addDays(7), ['reservation' => $reservation->id]);
                Mail::mailer('smtp')->to($reservation->contact_email)->send(new \App\Mail\EquipmentRequestUpdated($reservation, $details, $link));
            } catch (\Throwable $exception) {
                Log::error('Equipment update email delivery failed.', ['reservation_id' => $reservation->id, 'exception_class' => $exception::class]);
            }
        });
    }
}
