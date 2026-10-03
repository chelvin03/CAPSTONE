<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|StreamedResponse
    {
        $statuses = ['new', 'pending', 'validated', 'waiting_list', 'approved', 'completed', 'rejected', 'cancelled'];
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'category' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:'.implode(',', $statuses)],
            'export' => ['nullable', 'in:csv'],
        ]);
        $now = CarbonImmutable::now();
        $from = CarbonImmutable::parse($filters['date_from'] ?? $now->startOfMonth()->subMonths(11)->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'] ?? $now->endOfMonth()->toDateString())->startOfDay();
        if ($to->lt($from) || $from->diffInDays($to) > 1095) {
            throw \Illuminate\Validation\ValidationException::withMessages(['date_to' => 'Choose an end date on or after the start date, within three years.']);
        }
        $filters = array_merge($filters, ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
        unset($filters['export']);
        $base = Reservation::query()
            ->when($filters['facility_id'] ?? null, fn ($q, $id) => $q->where('facility_id', $id))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('reservation_type', $category))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $query = (clone $base)->whereDate('reservation_date', '>=', $from->toDateString())->whereDate('reservation_date', '<=', $to->toDateString());

        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($query): void {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Reference', 'Event', 'Facility', 'Reservation date', 'Category', 'Status', 'Expected attendees']);
                foreach ((clone $query)->with('facility')->orderBy('id')->lazy(500) as $row) {
                    $values = [$row->reference_number, $row->event_name, $row->facility?->facility_name ?? 'Unknown facility', $row->reservation_date->toDateString(), $row->reservation_type, $row->status, $row->expected_attendees];
                    fputcsv($file, array_map(fn ($value) => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".$value : $value, $values));
                }
                fclose($file);
            }, 'dashboard-reservations.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $records = (clone $query)->get(['facility_id', 'reservation_date', 'status', 'expected_attendees', 'created_at']);
        $totalReservations = $records->count();
        $counts = $records->countBy('status');
        $previousTo = $from->subDay();
        $previousFrom = $from->subDays((int) $from->diffInDays($to) + 1);
        $previousTotal = (clone $base)->whereDate('reservation_date', '>=', $previousFrom->toDateString())->whereDate('reservation_date', '<=', $previousTo->toDateString())->count();
        $change = $previousTotal > 0 ? round(($totalReservations - $previousTotal) / $previousTotal * 100, 1) : null;
        $byMonth = $records->countBy(fn ($r) => $r->reservation_date->format('Y-m'));
        $monthlyActivity = collect();
        for ($month = $from->startOfMonth(); $month->lte($to); $month = $month->addMonth()) {
            $monthlyActivity->push(['label' => $month->format('M'), 'year' => $month->format('Y'), 'count' => $byMonth->get($month->format('Y-m'), 0), 'from' => $month->max($from)->toDateString(), 'to' => $month->endOfMonth()->min($to)->toDateString()]);
        }
        $facilities = Facility::orderBy('facility_name')->get();
        $facilityDemand = $records->countBy('facility_id')->map(fn ($count, $id) => ['id' => $id, 'label' => $facilities->firstWhere('id', $id)?->facility_name ?? 'Unknown facility', 'count' => $count])->sortByDesc('count')->values();
        $weekdays = $records->countBy(fn ($r) => $r->reservation_date->dayOfWeekIso);
        $weekdayDemand = collect(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])->map(fn ($label, $index) => ['label' => $label, 'count' => $weekdays->get($index + 1, 0)]);
        $pending = $records->whereIn('status', ['new', 'pending', 'validated', 'waiting_list']);
        $oldPending = $pending->filter(fn ($r) => $r->created_at && $r->created_at->lte($now->subDays(7)))->count();

        return view('dashboard.admin', [
            'filters' => $filters, 'statuses' => $statuses, 'facilities' => $facilities,
            'categories' => Reservation::distinct()->orderBy('reservation_type')->pluck('reservation_type'),
            'totalReservations' => $totalReservations,
            'approvedReservations' => $counts->get('approved', 0),
            'completedEvents' => $counts->get('completed', 0),
            'pendingReservations' => $pending->count(),
            'cancelledReservations' => $counts->get('cancelled', 0),
            'approvalRate' => $totalReservations ? (int) round($counts->get('approved', 0) / $totalReservations * 100) : 0,
            'cancellationRate' => $totalReservations ? round($counts->get('cancelled', 0) / $totalReservations * 100, 1) : null,
            'expectedAttendees' => $records->whereIn('status', ['approved', 'completed'])->sum('expected_attendees'),
            'thisMonthReservations' => $records->filter(fn ($r) => $r->reservation_date->format('Y-m') === $now->format('Y-m'))->count(),
            'thisMonthCancellations' => $records->filter(fn ($r) => $r->status === 'cancelled' && $r->reservation_date->format('Y-m') === $now->format('Y-m'))->count(),
            'monthlyActivity' => $monthlyActivity, 'monthlyMaximum' => max(1, $monthlyActivity->max('count')),
            'facilityDemand' => $facilityDemand, 'weekdayDemand' => $weekdayDemand,
            'statusBreakdown' => collect($statuses)->map(fn ($status) => ['status' => $status, 'count' => $counts->get($status, 0)]),
            'previousTotal' => $previousTotal, 'previousFrom' => $previousFrom, 'previousTo' => $previousTo, 'change' => $change,
            'oldPending' => $oldPending,
            'recentReservations' => (clone $query)->with('facility')->latest('updated_at')->orderByDesc('id')->paginate(10)->withQueryString(),
        ]);
    }
}
