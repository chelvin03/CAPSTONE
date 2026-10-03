<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReservationReportService
{
    public function filters(Request $request): array
    {
        return $request->validate([
            'report_type' => ['required', 'in:reservation_detail,summary,priority_history'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:new,pending,validated,waiting_list,approved,rejected,cancelled,completed'],
            'category' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    public function records(array $filters): Collection
    {
        return Reservation::query()->with(['facility', 'user'])
            ->when(($filters['report_type'] ?? '') === 'priority_history', fn (Builder $query) => $query->whereNotNull('priority_number'))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('reservation_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('reservation_date', '<=', $date))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('reservation_type', $category))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->orderBy('reservation_date')->orderBy('start_time')->orderBy('id')->get();
    }

    public function summary(Collection $records): array
    {
        $total = $records->count();
        return [
            'total' => $total,
            'approved' => $records->where('status', 'approved')->count(),
            'cancelled' => $records->where('status', 'cancelled')->count(),
            'completed' => $records->where('status', 'completed')->count(),
            'attendees' => (int) $records->sum('expected_attendees'),
            'cancellation_rate' => $total > 0 ? $records->where('status', 'cancelled')->count() / $total : 0,
        ];
    }
}
