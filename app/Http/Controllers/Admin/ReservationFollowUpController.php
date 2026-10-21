<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Reservation, ReservationReminder};
use App\Services\ReservationFollowUpService;
use Illuminate\Http\Request;

class ReservationFollowUpController extends Controller
{
    public function index(ReservationFollowUpService $service)
    {
        $shortages = collect();
        Reservation::where('status', 'approved')->whereDate('reservation_date', '>=', now()->toDateString())->with('equipment')->get()->each(function ($reservation) use ($shortages) {
            foreach ($reservation->equipment as $item) {
                $available = app(\App\Services\EquipmentRequestService::class)->available($item, $reservation);
                if ((int) $item->pivot->quantity_approved > $available) $shortages->push(['reservation' => $reservation, 'equipment' => $item, 'available' => $available]);
            }
        });
        return view('admin.reservations.follow-up', ['followUps' => $service->candidates(),
            'equipmentShortages' => $shortages,
            'waitingRequests' => Reservation::where('status', 'waiting_list')->whereDate('reservation_date', '>=', now()->toDateString())->orderBy('priority_number')->orderBy('created_at')->get(),
            'reminderHistory' => ReservationReminder::with('reservation')->latest()->limit(30)->get()]);
    }

    public function send(Request $request, ReservationFollowUpService $service)
    {
        $data = $request->validate(['kind' => ['required', 'in:event,requirements,proposal'], 'id' => ['required', 'integer', 'min:1'], 'fingerprint' => ['required', 'string', 'size:64']]);
        $service->send($data['kind'], (int) $data['id'], $data['fingerprint'], $request->user());
        return back()->with('success', 'Reminder delivery attempted. Check its recorded delivery status below.');
    }
}
