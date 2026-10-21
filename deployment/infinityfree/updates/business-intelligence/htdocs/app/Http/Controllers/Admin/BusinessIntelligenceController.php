<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardRequest;
use App\Services\DashboardAnalyticsService;
use Illuminate\View\View;

class BusinessIntelligenceController extends Controller
{
    public function __invoke(DashboardRequest $request, DashboardAnalyticsService $analytics): View
    {
        return view('admin.analytics.index', $analytics->summarize($request->filters()));
    }
}
