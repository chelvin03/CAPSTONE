<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsRequest;
use App\Services\AnalyticsWorkspaceService;
use App\Services\FacilityUtilizationWorkspaceService;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __invoke(AnalyticsRequest $request, AnalyticsWorkspaceService $workspace, string $section = 'reservations'): View
    {
        $data = $workspace->summarize($request->filters());
        if ($section === 'utilization') {
            $data['facilityWorkspace'] = app(FacilityUtilizationWorkspaceService::class)->summarize($data,
                $request->safe()->only(['trend', 'weekday_metric', 'occupancy', 'outcomes', 'notes_order']));
        }

        return view('admin.analytics.index', $data + ['section' => $section]);
    }
}
