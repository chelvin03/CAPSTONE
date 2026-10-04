<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use App\Models\Reservation;
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
            'year' => ['nullable', 'regex:/^(all|[0-9]{4})$/', function ($attribute, $value, $fail) {
                if ($value !== 'all' && (int) $value !== (int) now()->year && ! Reservation::whereYear('reservation_date', $value)->exists()) {
                    $fail('Choose an available reservation year.');
                }
            }],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'period' => ['nullable', Rule::in(['today', 'month', 'year'])],
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
        $period = $filters['period'] ?? null;
        if ($period !== null) {
            $filters = [
                'date_from' => match ($period) {
                    'today' => $now->toDateString(),
                    'year' => $now->startOfYear()->toDateString(),
                    default => $now->startOfMonth()->toDateString(),
                },
                'date_to' => match ($period) {
                    'today' => $now->toDateString(),
                    'year' => $now->endOfYear()->toDateString(),
                    default => $now->endOfMonth()->toDateString(),
                },
                'grouping' => $period === 'year' ? 'monthly' : 'daily',
            ];
        }
        $calendarFilters = $period === null && ($this->filled('year') || ! $this->hasAny(['date_from', 'date_to']));
        if ($calendarFilters) {
            $filters['year'] = $filters['year'] ?? (string) $now->year;
            if ($filters['year'] === 'all') {
                $first = Reservation::min('reservation_date');
                $last = Reservation::max('reservation_date');
                $from = $first ? CarbonImmutable::parse($first)->startOfYear() : $now->startOfYear();
                $to = $last ? CarbonImmutable::parse($last)->endOfYear() : $now->endOfYear();
            } else {
                $from = $now->setDate((int) $filters['year'], (int) ($filters['month'] ?? 1), 1)->startOfDay();
                $to = isset($filters['month']) ? $from->endOfMonth() : $from->endOfYear();
            }
            $filters['date_from'] = $from->toDateString();
            $filters['date_to'] = $to->toDateString();
            $filters['grouping'] = 'monthly';
        }
        $from = CarbonImmutable::parse($filters['date_from'] ?? $now->startOfYear()->toDateString());
        $to = CarbonImmutable::parse($filters['date_to'] ?? $now->endOfYear()->toDateString());
        if ($to->lt($from) || (! $calendarFilters && $from->diffInDays($to) > 1095)) {
            throw ValidationException::withMessages(['date_to' => 'Choose an end date on or after the start date, within three years.'])
                ->redirectTo(route('admin.dashboard'));
        }
        unset($filters['export']);

        return array_merge($filters, ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'grouping' => $filters['grouping'] ?? 'daily']);
    }
}
