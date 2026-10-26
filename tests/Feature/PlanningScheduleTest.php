<?php

use App\Models\ActivityPreparation;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Services\PlanningScheduleService;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(10, 0));
    config(['gym.reservation.opening_time' => '07:00', 'gym.reservation.closing_time' => '24:00']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'MCST Main Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->booking = function (array $overrides = []) {
        return Reservation::create(array_replace([
            'reference_number' => 'PLAN-'.uniqid(), 'user_id' => $this->admin->id, 'facility_id' => $this->gym->id,
            'reservation_type' => 'internal', 'event_name' => 'Basketball Practice', 'event_type' => 'Sports Activities',
            'purpose' => 'Practice', 'contact_person' => 'Coach', 'contact_number' => '09123456789', 'expected_attendees' => 80,
            'reservation_date' => '2026-10-28', 'start_time' => '08:00', 'end_time' => '10:00', 'status' => 'approved',
        ], $overrides));
    };
    $this->actingAs($this->admin);
});

test('planning includes today and the next three days using live active gym records', function () {
    foreach (['2026-10-27', '2026-10-28', '2026-10-29', '2026-10-30'] as $date) {
        ($this->booking)(['reservation_date' => $date]);
    }
    ($this->booking)(['reservation_date' => '2026-10-31']);
    ($this->booking)(['reservation_date' => '2026-10-26']);
    foreach (['new', 'validated', 'waiting_list', 'pending', 'pencil'] as $status) {
        ($this->booking)(['status' => $status]);
    }
    foreach (['cancelled', 'rejected', 'completed', 'no_show'] as $status) {
        ($this->booking)(['status' => $status]);
    }
    $other = Facility::create(['facility_name' => 'Other Gym', 'capacity' => 100, 'status' => 'available']);
    ($this->booking)(['facility_id' => $other->id]);
    $response = $this->get(route('admin.planning.index'))->assertOk()->assertViewHas('total', 9)
        ->assertSee('Pending')->assertDontSee('View Schedule')->assertDontSee('Equipment requirements')
        ->assertDontSee('Acknowledgement receipt:')->assertDontSee('expected attendees');
    expect($response->viewData('days'))->toHaveCount(4);
    expect($response->viewData('days')->last()['date']->toDateString())->toBe('2026-10-30');
});

test('checklist persists completion and invalidates readiness when activity details change', function () {
    $booking = ($this->booking)();
    $snapshot = app(PlanningScheduleService::class)->snapshot($booking->load('equipment'));
    foreach (array_keys(PlanningScheduleService::TASKS) as $task) {
        $this->patch(route('admin.planning.update', $booking), ['task' => $task, 'completed' => 1, 'snapshot' => $snapshot])->assertSessionHasNoErrors()->assertRedirect();
    }
    $this->assertDatabaseCount('activity_preparations', 4);
    $this->get(route('admin.planning.index'))->assertViewHas('readyCount', 1);
    $booking->update(['expected_attendees' => 90]);
    $response = $this->get(route('admin.planning.index'))->assertOk()->assertViewHas('readyCount', 0)->assertSee('Activity details changed');
    expect($response->viewData('selected')['tasks']->where('stale', true))->toHaveCount(4);
    $this->patch(route('admin.planning.update', $booking), ['task' => 'venue', 'completed' => 1, 'snapshot' => $snapshot])->assertSessionHasErrors('preparation');
    expect(ActivityPreparation::where('reservation_id', $booking->id)->first()->updated_by)->toBe($this->admin->id);
});

test('equipment and administrator blocks require attention even with a checked checklist', function () {
    $booking = ($this->booking)();
    $equipment = Equipment::create(['equipment_name' => 'Chairs', 'total_quantity' => 100, 'unit' => 'pieces', 'status' => 'available']);
    $booking->equipment()->attach($equipment->id, ['quantity_requested' => 20, 'quantity_approved' => 0, 'status' => 'pending']);
    ScheduleBlock::create(['facility_id' => $this->gym->id, 'starts_on' => '2026-10-28', 'ends_on' => '2026-10-28', 'start_time' => '09:00', 'end_time' => '10:00', 'title' => 'Maintenance', 'created_by' => $this->admin->id]);
    $snapshot = app(PlanningScheduleService::class)->snapshot($booking->load('equipment'));
    foreach (array_keys(PlanningScheduleService::TASKS) as $task) {
        ActivityPreparation::create(['reservation_id' => $booking->id, 'task' => $task, 'completed' => true, 'snapshot_hash' => $snapshot, 'updated_by' => $this->admin->id]);
    }
    $response = $this->get(route('admin.planning.index'))->assertOk()->assertViewHas('readyCount', 0)->assertSee('Equipment requests need allocation');
    expect($response->viewData('selected')['reservation']->equipment->first()->equipment_name)->toBe('Chairs');
    expect($response->viewData('attention'))->toHaveCount(1);
    expect(implode(' ', $response->viewData('selected')['reasons']))->toContain('administrator block');
});

test('pending venue confirmation and unauthorized or out of window changes are rejected', function () {
    $booking = ($this->booking)(['status' => 'new']);
    $snapshot = app(PlanningScheduleService::class)->snapshot($booking->load('equipment'));
    $this->patch(route('admin.planning.update', $booking), ['task' => 'venue', 'completed' => 1, 'snapshot' => $snapshot])->assertSessionHasErrors('preparation');
    $outside = ($this->booking)(['reservation_date' => '2026-10-31']);
    $this->patch(route('admin.planning.update', $outside), ['task' => 'inspection', 'completed' => 1, 'snapshot' => app(PlanningScheduleService::class)->snapshot($outside->load('equipment'))])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('admin.planning.index'))->assertForbidden();
    $this->patch(route('admin.planning.update', $booking), ['task' => 'inspection', 'completed' => 1, 'snapshot' => $snapshot])->assertForbidden();
    $this->assertDatabaseCount('activity_preparations', 0);
});

test('planning window and empty dates work across a month boundary', function () {
    $this->travelTo(now()->setDate(2026, 10, 31));
    ($this->booking)(['reservation_date' => '2026-11-03']);
    ($this->booking)(['reservation_date' => '2026-11-04']);
    $this->get(route('admin.planning.index'))->assertOk()->assertViewHas('total', 1)->assertSee('No confirmed or pending activities.');
});

test('approved equipment shortages appear in attention without changing allocation records', function () {
    $booking = ($this->booking)();
    $equipment = Equipment::create(['equipment_name' => 'Speakers', 'total_quantity' => 1, 'unit' => 'pieces', 'status' => 'available']);
    $booking->equipment()->attach($equipment->id, ['quantity_requested' => 2, 'quantity_approved' => 2, 'status' => 'approved']);
    $this->get(route('admin.planning.index'))->assertOk()->assertSee('Approved equipment allocation exceeds current usable availability.');
    expect((int) $booking->equipment()->first()->pivot->quantity_approved)->toBe(2);
});

test('empty planning windows render safely and guests cannot access preparation records', function () {
    $this->get(route('admin.planning.index'))->assertOk()->assertViewHas('total', 0)->assertViewHas('selected', null)
        ->assertSee('No activities need preparation');
    auth()->logout();
    $this->get(route('admin.planning.index'))->assertRedirect(route('login'));
});
