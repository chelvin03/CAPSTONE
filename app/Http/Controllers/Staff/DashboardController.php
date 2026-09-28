<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\StaffMonitoringService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(StaffMonitoringService $monitoring): View
    {
        return view('staff.dashboard', $monitoring->dashboard());
    }
}
