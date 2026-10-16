<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\DashboardTrendService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', config('app.timezone')));
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->addBooking = function ($date, $status = 'approved') {
        return Reservation::create(['reference_number' => 'TREND-'.uniqid(), 'user_id' => $this->admin->id,
            'facility_id' => $this->gym->id, 'reservation_type' => 'internal', 'event_name' => 'Practice',
            'purpose' => 'Practice', 'contact_person' => 'Coordinator', 'contact_number' => '09123456789',
            'expected_attendees' => 50, 'reservation_date' => $date, 'start_time' => '09:00', 'end_time' => '11:00', 'status' => $status]);
    };
    $this->trendFilters = ['date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'grouping' => 'monthly'];
});

test('monthly and annual trends fill gaps and follow status scope', function () {
    ($this->addBooking)('2024-02-01');
    ($this->addBooking)('2026-02-01');
    ($this->addBooking)('2026-02-02', 'cancelled');
    $data = app(DashboardTrendService::class)->summarize([...$this->trendFilters, 'status' => 'approved'], app(DashboardAnalyticsService::class));
    expect($data['dashboardMonthlyTrend'])->toHaveCount(12)
        ->and($data['dashboardMonthlyTrend'][1]['count'])->toBe(1)
        ->and($data['dashboardMonthlyTrend'][2]['count'])->toBe(0)
        ->and($data['dashboardYearlyTrend'][1])->toBe(['label' => '2025', 'count' => 0])
        ->and($data['dashboardBusyMonths']->first()['years'])->toBe(2);
});

test('forecast includes zero months and excludes current and future scheduled reservations', function () {
    foreach (['2026-04-01', '2026-04-02', '2026-06-01', '2026-09-01', '2026-09-02', '2026-09-03', '2026-10-01', '2026-11-01'] as $date) {
        ($this->addBooking)($date);
    }
    $data = app(DashboardTrendService::class)->summarize($this->trendFilters, app(DashboardAnalyticsService::class));
    expect($data['dashboardForecastMonths'])->toBe(6)
        ->and($data['dashboardForecast'][0])->toBe(['label' => 'November 2026', 'estimate' => 1.0])
        ->and($data['dashboardForecast'][2]['label'])->toBe('January 2027');
});

test('short history has no forecast and calendar and category filters remain available', function () {
    ($this->addBooking)('2026-09-01');
    $this->actingAs($this->admin)->get(route('admin.dashboard', ['period' => 'today']))
        ->assertOk()->assertSee('Monthly Reservation Trend')
        ->assertSee('Yearly Reservation Trend')->assertSee('Insufficient history')
        ->assertSee('name="year"', false)->assertSee('name="month"', false)->assertSee('name="category"', false)
        ->assertViewHas('filters', fn ($filters) => $filters['date_from'] === '2026-10-12' && $filters['date_to'] === '2026-10-12');
});

test('online dashboard package renders without the old filter panel and matching table', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.dashboard', ['period' => 'year']))->assertOk();
    $html = view()->file(base_path('deployment/infinityfree/updates/dashboard-trends/htdocs/resources/views/dashboard/admin.blade.php'), $response->viewData())->render();
    expect($html)->toContain('This Month')->toContain('Monthly Reservation Trend')->toContain('Yearly Reservation Trend')
        ->not->toContain('aria-label="Dashboard filters"')->not->toContain('id="reservation-details"');
});
