<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Services\DashboardKpiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KpiTargetController extends Controller
{
    public function update(Request $request)
    {
        $rules = ['targets_reviewed' => ['accepted']];
        foreach (DashboardKpiService::TARGETS as $key => $definition) {
            $rules['kpi_'.$key] = ['nullable', 'numeric', 'min:0', 'max:'.$definition['max']];
        }
        $rules['kpi_overdue'][] = 'integer';
        $validated = $request->validate($rules);
        DB::transaction(function () use ($validated, $request): void {
            $old = SystemSetting::whereIn('key', array_map(fn ($key) => 'kpi_'.$key, array_keys(DashboardKpiService::TARGETS)))->pluck('value', 'key')->all();
            $new = [];
            foreach (DashboardKpiService::TARGETS as $key => $definition) {
                $name = 'kpi_'.$key;
                if (! array_key_exists($name, $validated)) continue;
                $new[$name] = $validated[$name] === null ? '' : (string) $validated[$name];
                SystemSetting::updateOrCreate(['key' => $name], ['value' => $new[$name]]);
            }
            SystemSetting::updateOrCreate(['key' => 'kpi_updated_at'], ['value' => now()->toIso8601String()]);
            AuditLog::recordEvent('analytics.kpi_targets_updated', oldValues: $old, newValues: $new, actor: $request->user());
        });
        return redirect()->route('admin.dashboard', $request->only(['year', 'month', 'day', 'date_from', 'date_to', 'all_time', 'grouping', 'category', 'status', 'requestor_type', 'facility_id', 'heat_scope']))
            ->with('success', 'KPI targets saved. Blank targets remain not configured.');
    }
}
