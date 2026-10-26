<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Reservation, ReservationStatusHistory};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationReviewController extends Controller
{
    public function update(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'action' => ['required', 'in:validate,waitlist,notes'],
            'reason' => ['required_if:action,waitlist', 'nullable', 'string', 'max:5000'],
            'admin_notes' => ['nullable', 'string', 'max:10000'],
        ]);
        DB::transaction(function () use ($request, $reservation, $data) {
            $booking = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($data['action'] === 'notes') {
                $booking->update(['admin_notes' => $data['admin_notes'] ?? null]);
                AuditLog::record('reservation.notes_updated', $booking);
                return;
            }
            $allowed = $data['action'] === 'validate' ? ['new', 'pending', 'pencil'] : ['new', 'pending', 'validated', 'pencil'];
            if (!in_array($booking->status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'The reservation status has changed. Reload this page before taking action.']);
            }
            $previous = $booking->status;
            $status = $data['action'] === 'validate' ? 'validated' : 'waiting_list';
            $booking->update(['status' => $status]);
            ReservationStatusHistory::create(['reservation_id' => $booking->id, 'changed_by' => $request->user()->id,
                'previous_status' => $previous, 'new_status' => $status,
                'remarks' => $data['reason'] ?? 'Reservation details and supporting documents reviewed.']);
            AuditLog::record('reservation.'.$status, $booking, ['previous_status' => $previous, 'reason' => $data['reason'] ?? null]);
        });
        return back()->with('success', match ($data['action']) {
            'validate' => 'Request validated.', 'waitlist' => 'Request moved to the waiting list.', default => 'Internal notes saved.',
        });
    }
}
