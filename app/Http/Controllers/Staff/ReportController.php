<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\AnalyticsReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $reservations = $this->query($filters)->with('facility')->get();
        $statusCounts = $reservations->countBy('status')->sortDesc();
        $facilityCounts = $reservations
            ->groupBy(fn (Reservation $reservation): string => $reservation->facility?->facility_name ?? 'Unassigned')
            ->map->count()
            ->sortDesc();

        return view('staff.reports.index', [
            'filters' => $filters,
            'totalReservations' => $reservations->count(),
            'approvedReservations' => $reservations->where('status', 'approved')->count(),
            'completedReservations' => $reservations->where('status', 'completed')->count(),
            'totalAttendees' => $reservations->sum('expected_attendees'),
            'statusCounts' => $statusCounts,
            'facilityCounts' => $facilityCounts,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $reservations = $this->query($filters)->with('facility')->orderBy('reservation_date')->get();

        return response()->streamDownload(function () use ($reservations): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Reference', 'Event', 'Facility', 'Date', 'Start Time', 'End Time', 'Attendees', 'Status']);

            foreach ($reservations as $reservation) {
                fputcsv($output, [
                    $reservation->reference_number,
                    $reservation->event_name,
                    $reservation->facility?->facility_name ?? 'Unassigned',
                    $reservation->reservation_date->format('Y-m-d'),
                    $reservation->start_time,
                    $reservation->end_time,
                    $reservation->expected_attendees,
                    ucwords(str_replace('_', ' ', $reservation->status)),
                ]);
            }

            fclose($output);
        }, 'reservation-analytics-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'in:weekly,monthly,annual'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);
        $filters = ['date_from' => $validated['date_from'], 'date_to' => $validated['date_to']];
        $reservations = $this->query($filters)->with('facility')->get();

        AnalyticsReport::create([
            'submitted_by' => $request->user()->id,
            'report_type' => $validated['report_type'],
            'period_start' => $validated['date_from'],
            'period_end' => $validated['date_to'],
            'total_reservations' => $reservations->count(),
            'approved_reservations' => $reservations->where('status', 'approved')->count(),
            'completed_reservations' => $reservations->where('status', 'completed')->count(),
            'total_attendees' => $reservations->sum('expected_attendees'),
            'status_counts' => $reservations->countBy('status')->sortDesc()->all(),
            'facility_counts' => $reservations->groupBy(fn (Reservation $reservation): string => $reservation->facility?->facility_name ?? 'Unassigned')->map->count()->sortDesc()->all(),
            'submitted_at' => now(),
        ]);

        return redirect()->route('staff.reports.index', $filters)
            ->with('success', ucfirst($validated['report_type']).' report submitted to the administrator.');
    }

    /** @return array{date_from: string|null, date_to: string|null} */
    private function filters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    /** @param array{date_from: string|null, date_to: string|null} $filters */
    private function query(array $filters): Builder
    {
        return Reservation::query()
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('reservation_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('reservation_date', '<=', $date));
    }
}
