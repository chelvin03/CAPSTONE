<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\StaffMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, StaffMonitoringService $monitoring): View
    {
        $filters = $request->validate([
            'year' => ['nullable', 'regex:/^(all|[1-9][0-9]{3})$/'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'day' => ['nullable', 'integer', 'between:1,31'],
        ]);
        $filters['year'] = $filters['year'] ?? 'all';
        if (isset($filters['day']) && ($filters['year'] === 'all' || ! isset($filters['month'])
            || ! checkdate((int) $filters['month'], (int) $filters['day'], (int) $filters['year']))) {
            throw ValidationException::withMessages(['day' => 'Choose a valid day within a specific year and month.']);
        }

        return view('staff.dashboard', $monitoring->dashboard($filters));
    }
}
