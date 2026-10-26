<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\EventOutcomeService;
use Illuminate\Http\Request;

class EventOutcomeController extends Controller
{
    public function store(Request $request, Reservation $reservation, EventOutcomeService $service)
    {
        $data = $request->validate([
            'outcome' => ['required', 'in:completed,no_show'],
            'actual_attendees' => ['required_if:outcome,completed', 'nullable', 'integer', 'min:0', 'max:'.\App\Support\GymCapacity::MAX_ATTENDEES],
            'remarks' => ['nullable', 'string', 'max:500'],
            'revision' => ['required', 'integer', 'min:0'],
            'correction_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->record($reservation, $request->user(), $data);
        return back()->with('success', 'Attendance record saved and included in reservation reports and BI statistics.');
    }
}
