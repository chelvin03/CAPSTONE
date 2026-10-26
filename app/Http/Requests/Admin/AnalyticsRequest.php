<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Support\Arr;

class AnalyticsRequest extends DashboardRequest
{
    protected $redirectRoute = 'admin.analytics';

    public function rules(): array
    {
        return parent::rules() + [
            'trend' => ['nullable', 'in:monthly,quarterly'],
            'weekday_metric' => ['nullable', 'in:hours,percentage'],
            'occupancy' => ['nullable', 'in:hour,period'],
            'outcomes' => ['nullable', 'in:all,ended'],
            'notes_order' => ['nullable', 'in:latest,oldest'],
        ];
    }

    public function filters(): array
    {
        return Arr::except(parent::filters(), ['trend', 'weekday_metric', 'occupancy', 'outcomes', 'notes_order']);
    }
}
