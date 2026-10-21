<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;

class WaitingListController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'event_type' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'export' => ['nullable', 'in:csv'],
        ]);
        $base = Reservation::query()->where('status', 'waiting_list');
        $stats = [
            'Total on Waiting List' => (clone $base)->count(),
            'Added Today' => (clone $base)->whereDate('created_at', today())->count(),
            'This Week' => (clone $base)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'This Month' => (clone $base)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ];
        $eventTypes = (clone $base)->select('event_type')->whereNotNull('event_type')->distinct()->orderBy('event_type')->pluck('event_type');
        // Position is calculated before filters and pagination, within the same requested slot.
        $query = (clone $base)->select('reservations.*')->selectSub(function ($q) {
            $q->from('reservations as ahead')->selectRaw('COUNT(*) + 1')
                ->where('ahead.status', 'waiting_list')
                ->whereColumn('ahead.facility_id', 'reservations.facility_id')
                ->whereColumn('ahead.reservation_date', 'reservations.reservation_date')
                ->whereColumn('ahead.start_time', 'reservations.start_time')
                ->whereColumn('ahead.end_time', 'reservations.end_time')
                ->where(function ($q) {
                    $q->whereColumn('ahead.created_at', '<', 'reservations.created_at')
                        ->orWhere(function ($q) {
                            $q->whereColumn('ahead.created_at', 'reservations.created_at')->whereColumn('ahead.id', '<', 'reservations.id');
                        });
                });
        }, 'queue_position')->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($q) use ($search) {
                foreach (['contact_person', 'contact_number', 'event_type', 'event_name', 'reference_number', 'additional_notes', 'admin_notes'] as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        })->when($filters['event_type'] ?? null, fn ($q, $type) => $q->where('event_type', $type))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('reservation_date', $date))
            ->orderBy('created_at')->orderBy('id');

        if (($filters['export'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($query) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Reference', 'Name', 'Contact Number', 'Event Type', 'Preferred Date', 'Start Time', 'End Time', 'Joined On', 'Status', 'Position']);
                foreach ($query->cursor() as $row) {
                    $values = [$row->reference_number, $row->contact_person, $row->contact_number, $row->event_type ?: $row->event_name,
                        $row->reservation_date->toDateString(), $row->start_time, $row->end_time, $row->created_at->toDateTimeString(), 'Waiting', $row->queue_position];
                    fputcsv($out, array_map(fn ($value) => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".$value : $value, $values));
                }
                fclose($out);
            }, 'waiting-list-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('admin.reservations.waiting-list', [
            'reservations' => $query->paginate(10)->withQueryString(),
            'stats' => $stats, 'eventTypes' => $eventTypes,
        ]);
    }
}
