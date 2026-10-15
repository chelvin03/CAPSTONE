<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->details = [
        'facility_id' => $this->gym->id, 'reservation_type' => 'internal',
        'event_name' => 'Tentative practice', 'purpose' => 'Practice',
        'contact_person' => 'Coordinator', 'contact_number' => '09123456789',
        'expected_attendees' => 50, 'reservation_date' => now()->addDays(5)->toDateString(),
        'start_time' => '09:00', 'end_time' => '11:00', 'pencil_booking' => 1,
    ];
});

test('admin pencil booking holds a slot and can be confirmed with history', function () {
    $this->actingAs($this->admin)->get(route('admin.reservations.index'))->assertOk()->assertSee('Pencil Booking');
    $this->post(route('admin.reservations.store'), $this->details)->assertSessionHasNoErrors();
    $booking = Reservation::sole();
    expect($booking->status)->toBe('pencil')->and($booking->approved_at)->toBeNull();
    $this->getJson(route('reservation.availability', ['date' => $this->details['reservation_date']]))
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.status', 'pencil');
    $this->post(route('admin.reservations.store'), $this->details)->assertSessionHasErrors('reservation_date');
    $this->get(route('admin.reservations.show', $booking))->assertOk()->assertSee('Confirm Pencil Booking')->assertSee('Cancel Reservation');
    $this->patch(route('admin.reservations.approve', $booking))->assertSessionHasNoErrors();
    expect($booking->fresh()->status)->toBe('approved');
    $this->assertDatabaseHas('reservation_status_histories', ['reservation_id' => $booking->id, 'previous_status' => 'pencil', 'new_status' => 'approved']);
});

test('cancelling pencil booking releases the slot and holds are not pending timeouts', function () {
    $this->actingAs($this->admin)->post(route('admin.reservations.store'), $this->details)->assertSessionHasNoErrors();
    $booking = Reservation::sole();
    $booking->update(['created_at' => now()->subDays(4)]);
    $this->artisan('reservations:auto-cancel-pending')->assertSuccessful();
    expect($booking->fresh()->status)->toBe('pencil');
    $this->patch(route('admin.reservations.cancel', $booking), ['cancellation_reason' => 'No longer needed'])->assertSessionHasNoErrors();
    $this->getJson(route('reservation.availability', ['date' => $this->details['reservation_date']]))->assertJsonCount(0);
    $this->post(route('admin.reservations.store'), $this->details)->assertSessionHasNoErrors();
});

test('pencil bookings reject priority override and maintenance blocks', function () {
    $this->actingAs($this->admin)->post(route('admin.reservations.store'), [...$this->details, 'priority_override' => 1])->assertSessionHasErrors('pencil_booking');
    ScheduleBlock::create(['facility_id' => $this->gym->id, 'title' => 'Maintenance', 'starts_on' => $this->details['reservation_date'], 'ends_on' => $this->details['reservation_date'], 'start_time' => '10:00', 'end_time' => '12:00', 'created_by' => $this->admin->id]);
    $this->post(route('admin.reservations.store'), $this->details)->assertSessionHasErrors('reservation_date');
    $this->assertDatabaseCount('reservations', 0);
});

test('staff cannot create pencil bookings', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->post(route('admin.reservations.store'), $this->details)->assertForbidden();
    $this->assertDatabaseCount('reservations', 0);
});

test('regular reservations cannot occupy pencil holds and confirmation rechecks conflicts', function () {
    $this->actingAs($this->admin)->post(route('admin.reservations.store'), $this->details)->assertSessionHasNoErrors();
    $booking = Reservation::sole();
    $this->post(route('admin.reservations.store'), [...$this->details, 'pencil_booking' => 0])->assertSessionHasErrors('reservation_date');
    $other = Reservation::create([
        ...collect($this->details)->except('pencil_booking')->all(),
        'reference_number' => 'PENCIL-CONFLICT', 'status' => 'approved', 'user_id' => $this->admin->id,
    ]);
    $this->patch(route('admin.reservations.approve', $booking))->assertSessionHasErrors('reservation_date');
    expect($booking->fresh()->status)->toBe('pencil');
});
