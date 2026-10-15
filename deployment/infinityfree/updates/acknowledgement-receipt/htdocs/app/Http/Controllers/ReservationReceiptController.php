<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationReceiptController extends Controller
{
    public function show(Request $request, Reservation $reservation)
    {
        abort_unless($request->user()->status === 'approved', 403);
        abort_unless(in_array($reservation->status, ['approved', 'completed'], true), 403, 'An acknowledgement receipt is available only for an approved reservation.');
        $reservation->load(['facility', 'approvedBy', 'equipment']);
        return response()->view('reservations.acknowledgement', [
            'reservation' => $reservation, 'receiptNumber' => 'AR-'.$reservation->reference_number,
            'preparedBy' => $request->user()->full_name,
            'backUrl' => route($request->user()->role === 'staff' ? 'staff.reservations.show' : 'admin.reservations.show', $reservation),
        ])->header('Cache-Control', 'private, no-store');
    }
}
