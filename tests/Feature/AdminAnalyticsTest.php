<?php

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use App\Models\User;

test('analytics views render empty periods without invented metrics', function (string $section, string $heading) {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.analytics', ['section' => $section]))
        ->assertOk()->assertSee($heading)->assertSee('No reservations match this period')
        ->assertViewHas('totalReservations', 0)->assertViewHas('approvalDays', null)
        ->assertViewHas('equipmentFulfillment', null);
})->with([
    ['reservations', 'Reservation Analytics'],
    ['operations', 'Operations & Service Analytics'],
    ['utilization', 'Facility Utilization Analytics'],
]);

test('analytics filters and activity groups use matching real reservations', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Analytics Gym', 'capacity' => 100, 'status' => 'available']);
    foreach (['2026-05-10', '2026-05-11', '2026-06-10'] as $index => $date) {
        $reservation = Reservation::create([
            'reference_number' => 'ANALYTICS-'.$index, 'user_id' => $admin->id, 'facility_id' => $gym->id,
            'reservation_type' => 'school', 'event_name' => 'Sports Practice', 'event_type' => 'Sports',
            'purpose' => 'Practice', 'contact_person' => 'Coach', 'contact_number' => '09123456789',
            'expected_attendees' => 50, 'reservation_date' => $date, 'start_time' => '09:00', 'end_time' => '11:00',
            'status' => 'approved', 'created_at' => '2026-05-01 08:00:00', 'approved_at' => '2026-05-02 08:00:00',
        ]);
        $reservation->forceFill(['created_at' => '2026-05-01 08:00:00'])->save();
    }
    $response = $this->actingAs($admin)->get(route('admin.analytics', ['section' => 'reservations',
        'date_from' => '2026-05-01', 'date_to' => '2026-05-31', 'facility_id' => $gym->id]))
        ->assertOk()->assertViewHas('totalReservations', 2)->assertViewHas('approvalDays', 1.0)
        ->assertViewHas('activities', fn ($rows) => $rows->count() === 1 && $rows->first()['count'] === 2);
    expect($response->viewData('months')->sum('total'))->toBe(2);
    expect($response->viewData('weekdayHours')->sum('count'))->toBe(4.0);
    $equipment = Equipment::create(['equipment_name' => 'Analytics Balls', 'total_quantity' => 10, 'unit' => 'pieces', 'status' => 'available']);
    $reservation->equipment()->attach($equipment->id, ['quantity_requested' => 5, 'quantity_approved' => 3]);
    $this->get(route('admin.analytics', ['section' => 'operations', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30']))
        ->assertOk()->assertSee('Analytics Balls')->assertViewHas('equipmentFulfillment', 60.0);
    $this->get(route('admin.analytics', ['section' => 'utilization', 'date_from' => '2026-05-01', 'date_to' => '2026-05-31']))
        ->assertOk()->assertViewHas('totalReservations', 2)->assertViewHas('unusedHours', 0.0);
});

test('analytics is restricted to administrators and validates date ranges', function () {
    $this->get(route('admin.analytics'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('admin.analytics'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.analytics', ['date_from' => '2026-05-31', 'date_to' => '2026-05-01']))
        ->assertRedirect(route('admin.analytics'))->assertSessionHasErrors('date_to');
    $this->get('/admin/analytics/unknown')->assertNotFound();
});

test('facility reference panels use consistent merged hours and working controls', function () {
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '12:00']);
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Reference Gym', 'capacity' => 100, 'status' => 'available']);
    foreach ([['08:00', '11:00'], ['10:00', '12:00']] as $index => [$start, $end]) {
        $booking = Reservation::create([
            'reference_number' => 'REFERENCE-'.$index, 'user_id' => $admin->id, 'facility_id' => $gym->id,
            'reservation_type' => 'school', 'event_name' => 'Practice', 'event_type' => 'Sports', 'purpose' => 'Practice',
            'contact_person' => 'Coach', 'contact_number' => '09123456789', 'expected_attendees' => 50,
            'reservation_date' => '2026-05-11', 'start_time' => $start, 'end_time' => $end, 'status' => 'approved',
        ]);
    }
    $booking->statusHistories()->create(['changed_by' => $admin->id, 'previous_status' => 'approved', 'new_status' => 'approved', 'remarks' => 'Reservation rescheduled: confirmed new time']);
    ScheduleBlock::create(['facility_id' => $gym->id, 'starts_on' => '2026-05-11', 'ends_on' => '2026-05-11', 'start_time' => '09:00', 'end_time' => '10:00', 'title' => 'Closed', 'created_by' => $admin->id]);
    $filters = ['section' => 'utilization', 'date_from' => '2026-05-11', 'date_to' => '2026-05-11'];
    $response = $this->actingAs($admin)->get(route('admin.analytics', $filters))->assertOk()
        ->assertSee('Utilization Rate (%)')->assertSee('Utilization by Weekday')->assertSee('Rescheduled')
        ->assertSee('<th>Date</th><th>Note</th><th>Type</th>', false)->assertDontSee('Outcome recording coverage')
        ->assertDontSee('View chart data')->assertViewHas('utilizedHours', 3.0)->assertViewHas('availableHours', 3.0);
    $workspace = $response->viewData('facilityWorkspace');
    expect($workspace['weekdays']->sum('count'))->toBe(3.0);
    expect(collect($workspace['heat'])->sum(fn ($slot) => array_sum($slot['counts'])))->toBe(3);
    expect($workspace['peakHours'])->toBe(2.0);
    expect($workspace['changes']['available'])->toBe(-25.0);
    expect($workspace['outcomeRows']->get('Rescheduled')['count'])->toBe(1);
    expect($workspace['outcomeRows']->sum('count'))->toBe(2);
    $controlled = $this->get(route('admin.analytics', $filters + ['trend' => 'quarterly', 'weekday_metric' => 'percentage', 'occupancy' => 'period', 'outcomes' => 'ended', 'notes_order' => 'oldest']))->assertOk()->viewData('facilityWorkspace');
    expect($controlled['months']->first()['label'])->toBe('2026-Q2');
    expect($controlled['weekdays']->first()['count'])->toBe(100.0);
    expect($controlled['heat'])->toHaveCount(1);
});

test('facility quarterly trend keeps unavailable hours unknown', function () {
    config(['gym.reservation.opening_time' => null]);
    $workspace = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.analytics', ['section' => 'utilization', 'trend' => 'quarterly']))
        ->assertOk()->viewData('facilityWorkspace');
    expect($workspace['months']->first()['availableHours'])->toBeNull();
    expect($workspace['months']->first()['utilizedHours'])->toBeNull();
    expect($workspace['heat'])->toBeEmpty();
    expect($workspace['peakLabel'])->toBe('Not available');
});
