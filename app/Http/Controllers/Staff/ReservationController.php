<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\StaffMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(Request $request, StaffMonitoringService $monitoring): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'], 'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in([...StaffMonitoringService::STATUSES, 'pending'])],
        ]);
        $query = $monitoring->records();
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('reference_number', 'like', '%'.$search.'%')->orWhere('event_name', 'like', '%'.$search.'%'));
        }
        if ($filters['date'] ?? null) {
            $monitoring->onDate($query, $filters['date']);
        }
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        return view('staff.reservation.index', [
            'filters' => $filters, 'statuses' => StaffMonitoringService::STATUSES,
            'reservations' => $monitoring->ordered($query)->paginate(15)->appends($filters),
        ]);
    }

    public function show(string $reservation, StaffMonitoringService $monitoring): View
    {
        return view('staff.reservation.show', ['reservation' => $monitoring->records()->findOrFail($reservation)]);
    }
}
