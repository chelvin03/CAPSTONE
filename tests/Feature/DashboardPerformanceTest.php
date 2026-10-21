<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\DashboardPerformanceService;

function performanceBooking(Facility $gym, array $overrides = []): Reservation
{
    return Reservation::create(array_merge(['reference_number' => 'PERF-'.uniqid(), 'facility_id' => $gym->id,
        'reservation_type' => 'internal', 'event_name' => 'Sports', 'purpose' => 'Practice',
        'contact_person' => 'Requestor', 'contact_number' => '09123456789', 'expected_attendees' => 100,
        'reservation_date' => '2026-10-20', 'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved'], $overrides));
}

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 18)->startOfDay());
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->scope = ['date_from' => '2026-10-20', 'date_to' => '2026-10-20', 'grouping' => 'daily'];
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '17:00']);
});

test('monthly utilization merges overlapping bookings and subtracts blocks', function () {
    performanceBooking($this->gym);
    performanceBooking($this->gym, ['start_time' => '10:00', 'end_time' => '12:00']);
    ScheduleBlock::create(['created_by' => $this->admin->id, 'facility_id' => $this->gym->id,
        'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20', 'start_time' => '10:00', 'end_time' => '11:00', 'title' => 'Maintenance']);
    $result = app(DashboardPerformanceService::class)->summarize($this->scope, app(DashboardAnalyticsService::class))['performance'];
    expect($result['months'][0]['utilizedHours'])->toBe(2.0)
        ->and($result['months'][0]['availableHours'])->toBe(8.0)
        ->and($result['months'][0]['utilizationRate'])->toBe(25.0);
});

test('processing uses earliest valid decision and distinguishes cancellation attribution', function () {
    $first = performanceBooking($this->gym, ['approved_at' => now()->addHours(48)]);
    ReservationStatusHistory::create(['reservation_id' => $first->id, 'new_status' => 'approved'])->forceFill(['created_at' => now()->addHours(12)])->save();
    $cancelled = performanceBooking($this->gym, ['status' => 'cancelled', 'cancelled_at' => now()->addHours(36), 'cancellation_reason' => 'Plans changed']);
    ReservationStatusHistory::create(['reservation_id' => $cancelled->id, 'new_status' => 'cancelled',
        'remarks' => 'Cancelled by requestor: Plans changed'])->forceFill(['created_at' => now()->addHours(36)])->save();
    performanceBooking($this->gym, ['status' => 'cancelled', 'cancelled_by' => $this->admin->id, 'cancellation_reason' => 'Priority event']);
    performanceBooking($this->gym, ['status' => 'cancelled']);
    performanceBooking($this->gym, ['status' => 'new'])->forceFill(['created_at' => now()->subDays(8)])->save();
    $p = app(DashboardPerformanceService::class)->summarize($this->scope, app(DashboardAnalyticsService::class))['performance'];
    expect($p['processingMedian'])->toBe(24.0)->and($p['processingSample'])->toBe(2)
        ->and($p['processingMissing'])->toBe(3)->and($p['pending']['7+ days'])->toHaveCount(1)
        ->and($p['sources']['Requestor'])->toHaveCount(1)->and($p['sources']['Administrator'])->toHaveCount(1)
        ->and($p['sources']['Unknown / legacy'])->toHaveCount(1)->and($p['reasons']['Plans changed'])->toHaveCount(1)
        ->and($p['months'][0]['cancellationRate'])->toBe(60.0);
});

test('performance preserves dashboard charts and exports filtered evidence', function () {
    $in = performanceBooking($this->gym, ['status' => 'cancelled', 'cancellation_reason' => '<script>unsafe</script>']);
    $out = performanceBooking($this->gym, ['reservation_date' => '2026-09-20']);
    $response = $this->actingAs($this->admin)->get(route('admin.dashboard', ['year' => 2026, 'month' => 10]))
        ->assertOk()->assertSee('Operational performance analytics')->assertSee('Monthly Reservation Trend')
        ->assertSee('Insights and suggested actions')->assertSee('Required-field Completeness Overview')
        ->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false);
    $p = $response->viewData('performance');
    expect($p['months'][0]['records']->pluck('id')->all())->toBe([$in->id]);
    $csv = $this->get(route('admin.dashboard', ['year' => 2026, 'month' => 10, 'export' => 'csv']))->assertOk()->streamedContent();
    expect($csv)->toContain('Performance analysis', 'processingMedian', 'Unknown / legacy');
});

test('empty performance data does not invent zero rates', function () {
    $p = app(DashboardPerformanceService::class)->summarize($this->scope, app(DashboardAnalyticsService::class))['performance'];
    expect($p['processingMedian'])->toBeNull()->and($p['approvalRate'])->toBeNull()
        ->and($p['months'][0]['cancellationRate'])->toBeNull()->and($p['reasons'])->toBeEmpty();
});
