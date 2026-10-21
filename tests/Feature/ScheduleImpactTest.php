<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(now()->setDate(2026, 10, 18)->startOfDay());
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->make = function ($status = 'approved', $start = '09:00', $end = '11:00') {
        return Reservation::create(['reference_number' => 'IMPACT-'.strtoupper(uniqid()), 'facility_id' => $this->gym->id,
            'reservation_type' => 'internal', 'event_name' => 'School Event', 'purpose' => 'Sports', 'contact_person' => 'Requestor',
            'contact_email' => 'notice@example.com', 'contact_number' => '09123456789', 'expected_attendees' => 100,
            'reservation_date' => '2026-10-20', 'start_time' => $start, 'end_time' => $end, 'status' => $status]);
    };
    $this->block = ['title' => 'Roof maintenance', 'reason' => 'Urgent repairs', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20'];
    $this->actingAs($this->admin);
});

test('schedule displays actionable pending and blocked warnings without leaking contact details', function () {
    $first = ($this->make)('new');
    ($this->make)('validated', '10:00', '12:00');
    ScheduleBlock::create(['created_by' => $this->admin->id, 'facility_id' => $this->gym->id,
        'title' => 'Roof maintenance', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20']);
    $response = $this->get(route('admin.schedule.index', ['month' => '2026-10', 'date' => '2026-10-20', 'status' => 'approved']))->assertOk()
        ->assertSee('Resolve Conflict / Reschedule')->assertDontSee('notice@example.com');
    $issues = $response->viewData('days')['2026-10-20']['issues'];
    expect($issues)->toHaveCount(3)->and($issues[0]['title'])->toBe('Unconfirmed requests need schedule review')
        ->and($issues[0]['description'])->toContain('not a confirmed double booking')
        ->and($issues[0]['bookings'][0]['url'])->toBe(route('admin.reservations.show', $first).'#conflict-resolution');
});

test('block preview identifies email recipients and leaves the database and mail untouched', function () {
    ($this->make)();
    ($this->make)('new');
    $preview = $this->postJson(route('admin.schedule.blocks.preview'), $this->block)->assertOk()->assertJsonCount(2, 'rows');
    expect($preview->json('rows.0.email'))->toBe('notice@example.com')
        ->and($preview->json('rows.1.email'))->toContain('No cancellation email')
        ->and(ScheduleBlock::count())->toBe(0)->and(Reservation::where('status', 'approved')->count())->toBe(1);
    Mail::assertNothingSent();
});

test('blocks affecting bookings require confirmation and then cancel only approved records', function () {
    $approved = ($this->make)();
    $pending = ($this->make)('new');
    $this->post(route('admin.schedule.blocks.store'), $this->block)->assertSessionHasErrors('impact_token');
    expect(ScheduleBlock::count())->toBe(0);
    $this->post(route('admin.schedule.blocks.store'), previewedBlock($this->block))->assertSessionHasNoErrors();
    expect($approved->refresh()->status)->toBe('cancelled')->and($pending->refresh()->status)->toBe('new');
    Mail::assertSentCount(1);
});

test('a newly approved booking invalidates an earlier block preview', function () {
    ($this->make)();
    $data = previewedBlock($this->block);
    $new = ($this->make)('approved', '13:00', '15:00');
    $this->post(route('admin.schedule.blocks.store'), $data)->assertSessionHasErrors('impact_token');
    expect(ScheduleBlock::count())->toBe(0)->and($new->refresh()->status)->toBe('approved');
    Mail::assertNothingSent();
});

test('changed block details and expired preview tokens are refused', function () {
    ($this->make)();
    $data = previewedBlock($this->block);
    $this->post(route('admin.schedule.blocks.store'), [...$data, 'reason' => 'Changed reason'])->assertSessionHasErrors('impact_token');
    $this->travel(11)->minutes();
    $this->post(route('admin.schedule.blocks.store'), $data)->assertSessionHasErrors('impact_token');
    expect(ScheduleBlock::count())->toBe(0);
});

test('reschedule preview exposes conflicts and full duration alternatives before force is enabled', function () {
    $moving = ($this->make)('approved', '17:00', '24:00');
    ($this->make)();
    $preview = $this->postJson(route('admin.reservations.reschedule-preview', $moving), ['reservation_date' => '2026-10-20',
        'start_time' => '09:00', 'force_assign' => 0, 'reason' => 'School event'])->assertOk()->assertJsonPath('can_apply', false);
    expect($preview->json('rows'))->toHaveCount(2)->and($preview->json('alternatives'))->not->toBeEmpty();
    foreach ($preview->json('alternatives') as $choice) {
        $minutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        expect($minutes($choice['end']) - $minutes($choice['start']))->toBe(420);
    }
    Mail::assertNothingSent();
});

test('force assignment requires a matching preview and sends notices only after confirmation', function () {
    $moving = ($this->make)('approved', '14:00', '16:00');
    $other = ($this->make)();
    $data = ['reservation_date' => '2026-10-20', 'start_time' => '09:00', 'force_assign' => 1,
        'conflict_resolution' => 'cancelled', 'reason' => 'Priority school activity'];
    $this->patch(route('admin.reservations.reschedule', $moving), $data)->assertSessionHasErrors('impact_token');
    expect($other->refresh()->status)->toBe('approved');
    $this->patch(route('admin.reservations.reschedule', $moving), previewedReschedule($moving, $data))->assertSessionHasNoErrors();
    expect($other->refresh()->status)->toBe('cancelled');
    Mail::assertSentCount(2);
});

test('another admin cannot use someone elses preview', function () {
    ($this->make)();
    $data = previewedBlock($this->block);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.schedule.blocks.store'), $data)
        ->assertSessionHasErrors('impact_token');
    expect(ScheduleBlock::count())->toBe(0);
});

test('month long blocks fail on preview and save and staff cannot preview', function () {
    $data = [...$this->block, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31'];
    $this->postJson(route('admin.schedule.blocks.preview'), $data)->assertUnprocessable()->assertJsonValidationErrors('ends_on');
    $this->post(route('admin.schedule.blocks.store'), $data)->assertSessionHasErrors('ends_on');
    $this->actingAs(User::factory()->create(['role' => 'staff']))->postJson(route('admin.schedule.blocks.preview'), $this->block)->assertForbidden();
});
