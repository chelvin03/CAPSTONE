<?php

namespace App\Http\Controllers;

use App\Models\{AuditLog, Equipment, EquipmentReturn, Reservation};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentReturnController extends Controller
{
    public function store(Request $request, Reservation $reservation, Equipment $equipment)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'staff'], true) && $request->user()->status === 'approved', 403);
        $data = $request->validate([
            'issued' => ['required', 'integer', 'min:0'], 'returned_good' => ['required', 'integer', 'min:0'],
            'damaged' => ['required', 'integer', 'min:0'], 'missing' => ['required', 'integer', 'min:0'],
            'restored' => ['required', 'integer', 'min:0'], 'revision' => ['required', 'integer', 'min:0'],
            'remarks' => ['required', 'string', 'max:2000'], 'correction_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $reservation, $equipment, $data) {
            app(\App\Services\EquipmentRequestService::class)->lockInventory();
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $approved = $reservation->equipment()->where('equipment.id', $equipment->id)->firstOrFail();
            $existing = EquipmentReturn::where('reservation_id', $reservation->id)->where('equipment_id', $equipment->id)->lockForUpdate()->first();
            if ((!$existing && !in_array($reservation->status, ['approved', 'completed', 'no_show'], true))
                || app(\App\Services\EventOutcomeService::class)->endAt($reservation)->isFuture()) {
                throw ValidationException::withMessages(['returns' => 'Record equipment returns after the scheduled end of an approved event.']);
            }
            if ((int) $data['revision'] !== ($existing?->revision ?? 0)) throw ValidationException::withMessages(['returns' => 'The return record changed. Reload before saving.']);
            if ($existing) {
                abort_unless($request->user()->role === 'admin', 403);
                if (blank($data['correction_reason'] ?? null)) throw ValidationException::withMessages(['correction_reason' => 'Explain the correction or recovery of stock.']);
            }
            if ($data['issued'] > (int) $approved->pivot->quantity_approved
                || $data['returned_good'] + $data['damaged'] + $data['missing'] !== (int) $data['issued']
                || $data['restored'] > $data['damaged'] + $data['missing']) {
                throw ValidationException::withMessages(['returns' => 'Good returns + damaged + missing must equal issued quantity, which cannot exceed approved quantity. Restored stock cannot exceed damaged + missing.']);
            }
            if ($data['restored'] > 0 && $request->user()->role !== 'admin') abort(403, 'Only administrators may restore recovered or repaired stock.');
            $before = $existing?->toArray();
            $record = EquipmentReturn::updateOrCreate(['reservation_id' => $reservation->id, 'equipment_id' => $equipment->id],
                array_diff_key($data, ['correction_reason' => true]) + ['recorded_by' => $request->user()->id]);
            $record->update(['revision' => ($existing?->revision ?? 0) + 1]);
            AuditLog::record('equipment.return_recorded', $reservation, ['equipment_id' => $equipment->id, 'previous' => $before, 'current' => $record->toArray(), 'correction_reason' => $data['correction_reason'] ?? null]);
        });
        return back()->with('success', 'Equipment return saved. Unresolved damaged/missing units are excluded from usable stock. Review affected upcoming equipment allocations.');
    }
}
