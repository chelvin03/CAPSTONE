<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();
        $totalReservations = Reservation::count();
        $approvedReservations = Reservation::where('status', 'approved')->count();

        $monthlyActivity = collect(range(11, 0))
            ->map(fn (int $monthsAgo): CarbonImmutable => $now->subMonths($monthsAgo)->startOfMonth())
            ->push($monthStart)
            ->map(fn (CarbonImmutable $month): array => [
                'label' => $month->format('M'),
                'year' => $month->format('Y'),
                'count' => Reservation::whereBetween('reservation_date', [
                    $month->toDateString(),
                    $month->endOfMonth()->toDateString(),
                ])->count(),
            ]);

        return view('dashboard.admin', [
            'totalReservations' => $totalReservations,
            'thisMonthReservations' => Reservation::whereBetween('reservation_date', [$monthStart->toDateString(), $monthEnd->toDateString()])->count(),
            'pendingReservations' => Reservation::whereIn('status', ['new', 'pending', 'validated', 'waiting_list'])->count(),
            'approvedReservations' => $approvedReservations,
            'approvalRate' => $totalReservations > 0 ? (int) round(($approvedReservations / $totalReservations) * 100) : 0,
            'cancelledReservations' => Reservation::where('status', 'cancelled')->count(),
            'thisMonthCancellations' => Reservation::where('status', 'cancelled')->whereBetween('reservation_date', [$monthStart->toDateString(), $monthEnd->toDateString()])->count(),
            'facilityCount' => Facility::count(),
            'availableEquipment' => Equipment::where('status', 'available')->sum('total_quantity'),
            'completedEvents' => Reservation::where('status', 'completed')->count(),
            'recentReservations' => Reservation::latest('updated_at')->limit(5)->get(),
            'monthlyActivity' => $monthlyActivity,
            'monthlyMaximum' => max(1, (int) $monthlyActivity->max('count')),
        ]);
    }
}
