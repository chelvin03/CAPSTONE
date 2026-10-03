<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\StaffMonitoringService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request, StaffMonitoringService $monitoring): View
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $now = CarbonImmutable::now(config('app.timezone'));
        $date = $validated['date'] ?? $now->toDateString();
        $query = $monitoring->onDate($monitoring->records(), $date)->whereIn('status', ['approved', 'completed']);

        return view('staff.schedules.index', [
            'date' => $date, 'today' => $now->toDateString(),
            'reservations' => $monitoring->ordered($query)->paginate(15)->appends(['date' => $date]),
            'facilities' => $monitoring->facilities($now),
            'blocks' => $monitoring->blocks($date)->orderBy('start_time')->paginate(10, ['*'], 'blocks_page')->appends(['date' => $date]),
        ]);
    }
}
