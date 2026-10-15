<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;

test('the existing main gym record is used without renaming or replacing it', function () {
    $gym = Facility::create(['facility_name' => 'MCST Main Gymnasium', 'capacity' => 200, 'status' => 'available']);
    Facility::create(['facility_name' => 'Other Court', 'capacity' => 100, 'status' => 'available']);

    expect(Facility::gymId())->toBe($gym->id);
    $this->get(route('reservation.create'))->assertOk();
    $this->getJson(route('reservation.availability', ['date' => '2026-12-01']))->assertOk();
});

test('availability always uses the gym even when another facility is supplied', function () {
    $gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 200, 'status' => 'available']);
    $other = Facility::create(['facility_name' => 'Other Court', 'capacity' => 100, 'status' => 'available']);
    Reservation::create([
        'reference_number' => 'GYM-ONLY', 'facility_id' => $gym->id,
        'reservation_type' => 'internal', 'event_name' => 'Practice', 'purpose' => 'Practice',
        'contact_person' => 'Requestor', 'contact_number' => '09123456789',
        'expected_attendees' => 20, 'reservation_date' => '2026-12-01',
        'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'approved',
    ]);

    $this->getJson(route('reservation.availability', ['date' => '2026-12-01']))
        ->assertOk()->assertJsonCount(1);
    $this->getJson(route('reservation.availability', ['facility_id' => $other->id, 'date' => '2026-12-01']))
        ->assertOk()->assertJsonCount(1);
});

test('admin bookings automatically use the gym and facility management is removed', function () {
    $gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 200, 'status' => 'available']);
    $other = Facility::create(['facility_name' => 'Other Court', 'capacity' => 100, 'status' => 'available']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $this->get('/admin/facilities')->assertNotFound();
    $this->post('/admin/facilities', [])->assertNotFound();
    $this->get(route('admin.reservations.create'))->assertOk()->assertDontSee('Select facility');
    $this->post(route('admin.reservations.store'), [
        'facility_id' => $other->id, 'reservation_type' => 'internal',
        'event_name' => 'Gym Practice', 'purpose' => 'Practice',
        'contact_person' => 'Requestor', 'contact_number' => '09123456789',
        'expected_attendees' => 20, 'reservation_date' => now()->addDays(7)->toDateString(),
        'start_time' => '09:00', 'end_time' => '10:00',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('reservations', ['event_name' => 'Gym Practice', 'facility_id' => $gym->id]);
    $this->assertDatabaseHas('facilities', ['id' => $other->id]);
});
