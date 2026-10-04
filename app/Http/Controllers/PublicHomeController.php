<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ScheduleBlock;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function index(): View
    {
        return view('public.home', $this->bookings(now()->startOfDay(), now()->addDays(30)->endOfDay(), 5));
    }

    public function schedule(Request $request): View
    {
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $start = Carbon::createFromFormat('!Y-m', $validated['month'] ?? now()->format('Y-m'));

        return view('public.schedule', [
            ...$this->bookings($start, $start->copy()->endOfMonth()),
            'month' => $start->format('Y-m'),
            'monthLabel' => $start->format('F Y'),
        ]);
    }

    private function bookings(Carbon $start, Carbon $end, ?int $limit = null): array
    {
        try {
            // Select only public scheduling fields, never names, contacts or event details.
            $query = Reservation::query()->with('facility:id,facility_name')
                ->where('status', 'approved')->whereBetween('reservation_date', [$start->toDateString(), $end->toDateString()])
                ->orderBy('reservation_date')->orderBy('start_time');
            if ($limit !== null) {
                $query->limit($limit);
            }
            $bookings = $query->get(['facility_id', 'reservation_date', 'start_time', 'end_time']);
            $query = ScheduleBlock::query()->with('facility:id,facility_name')
                ->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)->orderBy('starts_on');
            if ($limit !== null) {
                $query->limit($limit);
            }

            return ['bookings' => $bookings, 'blocks' => $query->get(['facility_id', 'starts_on', 'ends_on', 'start_time', 'end_time']), 'scheduleUnavailable' => false];
        } catch (QueryException $exception) {
            Log::warning('Public schedule unavailable.', ['exception_code' => $exception->getCode()]);

            return ['bookings' => collect(), 'blocks' => collect(), 'scheduleUnavailable' => true];
        }
    }
}
