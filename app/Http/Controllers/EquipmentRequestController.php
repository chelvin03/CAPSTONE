<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\EquipmentUpdate;
use App\Services\EquipmentRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EquipmentRequestController extends Controller
{
    public function review(Request $request, Reservation $reservation, Equipment $equipment, EquipmentRequestService $service): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate([
            'action' => ['required', 'in:reply,approve,reject'],
            'quantity_approved' => ['required_if:action,approve', 'nullable', 'integer', 'min:1'],
            'quantity_offered' => ['required_if:action,reply', 'nullable', 'integer', 'min:0'],
            'remarks' => ['required_if:action,reply,reject', 'nullable', 'string', 'max:5000'],
            'requires_response' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($service, $reservation, $equipment, $data): void {
            $service->lockInventory();
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $equipment = Equipment::findOrFail($equipment->id);
            $item = $reservation->equipment()->where('equipment.id', $equipment->id)->firstOrFail();
            if ($reservation->reservation_type !== 'internal' || in_array($reservation->status, ['cancelled', 'rejected', 'completed'], true)) {
                throw ValidationException::withMessages(['equipment' => 'Only active Internal MCST reservations may have equipment reviewed.']);
            }
            $available = $service->available($equipment, $reservation);
            $quantity = (int) ($data['quantity_offered'] ?? $data['quantity_approved'] ?? 0);
            $details = ['equipment_id' => $equipment->id, 'equipment_name' => $equipment->equipment_name,
                'requested' => (int) $item->pivot->quantity_requested, 'available' => $available, 'quantity' => $quantity,
                'message' => $data['remarks'] ?? 'Your equipment request has been approved.', 'action' => $data['action']];
            if ($data['action'] === 'approve') {
                $service->approveQuantity($reservation, $equipment, $quantity);
                if (isset($data['remarks'])) $reservation->equipment()->updateExistingPivot($equipment->id, ['remarks' => $data['remarks'], 'reply_sent_at' => now()]);
            } elseif ($data['action'] === 'reject') {
                $reservation->equipment()->updateExistingPivot($equipment->id, ['status' => 'rejected', 'quantity_approved' => 0,
                    'remarks' => $data['remarks'], 'reply_sent_at' => now(), 'requires_response' => false, 'latest_offer_id' => null]);
                AuditLog::record('equipment.rejected', $reservation, $details);
            } else {
                if ($quantity > $available || $quantity > $item->pivot->quantity_requested || (($data['requires_response'] ?? false) && $quantity === 0)) {
                    throw ValidationException::withMessages(['quantity_offered' => 'An offer cannot exceed requested or available stock. Confirmation requires a positive offer.']);
                }
                $offerId = (string) Str::uuid();
                $reservation->equipment()->updateExistingPivot($equipment->id, [
                    'quantity_offered' => $quantity, 'quantity_approved' => null,
                    'status' => $quantity === 0 ? 'unavailable' : ($quantity < $item->pivot->quantity_requested ? 'partially_available' : 'available'),
                    'remarks' => $data['remarks'], 'reply_sent_at' => now(), 'requires_response' => $data['requires_response'] ?? false,
                    'latest_offer_id' => $offerId, 'requestor_response' => null, 'requestor_responded_at' => null,
                ]);
                $details += ['offer_id' => $offerId, 'requires_response' => (bool) ($data['requires_response'] ?? false)];
                AuditLog::record('equipment.reply_sent', $reservation, $details);
            }
            $service->publish($reservation, $details);
        });
        return back()->with('success', 'Equipment review saved. The requestor update is recorded in tracking; email delivery was attempted.');
    }

    public function access(Request $request, Reservation $reservation): RedirectResponse
    {
        // The signed route is sent only to the reservation's verified email address.
        $references = $request->session()->get('public_reservation_references', []);
        $request->session()->put('public_reservation_references', array_values(array_unique([...$references, $reservation->reference_number])));
        return redirect()->route('reservation.track', ['reference' => $reservation->reference_number]);
    }

    public function respond(Request $request, Reservation $reservation, Equipment $equipment, EquipmentRequestService $service): RedirectResponse
    {
        abort_unless(in_array($reservation->reference_number, $request->session()->get('public_reservation_references', []), true), 403);
        $data = $request->validate(['response' => ['required', 'in:accepted,declined'], 'offer_id' => ['required', 'uuid']]);
        DB::transaction(function () use ($service, $reservation, $equipment, $data): void {
            $service->lockInventory();
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $item = $reservation->equipment()->where('equipment.id', $equipment->id)->firstOrFail();
            if ($reservation->reservation_type !== 'internal' || in_array($reservation->status, ['cancelled', 'rejected', 'completed'], true)
                || !$item->pivot->requires_response || $item->pivot->latest_offer_id !== $data['offer_id']
                || $item->pivot->requestor_response !== null || !in_array($item->pivot->status, ['available', 'partially_available'], true)) {
                throw ValidationException::withMessages(['equipment' => 'This equipment offer is no longer awaiting a response.']);
            }
            $reservation->equipment()->updateExistingPivot($equipment->id, [
                'requestor_response' => $data['response'], 'requestor_responded_at' => now(),
                'status' => $data['response'] === 'accepted' ? 'accepted_by_requestor' : 'declined_by_requestor', 'quantity_approved' => null,
            ]);
            $message = 'Reservation '.$reservation->reference_number.' '.($data['response'] === 'accepted' ? 'accepted the offer of '.$item->pivot->quantity_offered.' '.$item->equipment_name : 'declined the equipment request for '.$item->equipment_name).'.';
            $details = ['message' => $message, 'equipment_id' => $equipment->id, 'response' => $data['response'], 'reference' => $reservation->reference_number, 'reservation_id' => $reservation->id, 'audience' => 'admin'];
            $service->publish($reservation, $details, false);
            foreach (User::where('role', 'admin')->get() as $admin) $admin->notify(new EquipmentUpdate($details));
            AuditLog::record('equipment.requestor_responded', $reservation, $details);
        });
        return back()->with('success', 'Your response has been recorded and the Gym Administrator notified. Final equipment approval remains subject to availability.');
    }
}
