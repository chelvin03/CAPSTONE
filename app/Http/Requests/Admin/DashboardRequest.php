<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashboardRequest extends FormRequest
{
    protected $redirectRoute = 'admin.dashboard';

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'category' => ['nullable', 'string', 'max:100', Rule::exists('reservations', 'event_type')],
            'requestor_type' => ['nullable', 'string', 'max:50', Rule::exists('reservations', 'reservation_type')],
            'status' => ['nullable', Rule::in(['new', 'validated', 'approved', 'rejected', 'waiting_list', 'cancelled', 'completed', 'pending'])],
            'grouping' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'annual'])],
            'export' => ['nullable', Rule::in(['csv'])],
        ];
    }

    public function filters(): array
    {
        $filters = array_filter($this->validated(), fn ($value) => $value !== null && $value !== '');
        $now = CarbonImmutable::now();
        $from = CarbonImmutable::parse($filters['date_from'] ?? $now->startOfMonth()->subMonths(11)->toDateString());
        $to = CarbonImmutable::parse($filters['date_to'] ?? $now->endOfMonth()->toDateString());
        if ($to->lt($from) || $from->diffInDays($to) > 1095) {
            throw ValidationException::withMessages(['date_to' => 'Choose an end date on or after the start date, within three years.'])
                ->redirectTo(route('admin.dashboard'));
        }
        unset($filters['export']);

        return array_merge($filters, ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'grouping' => $filters['grouping'] ?? 'monthly']);
    }
}
