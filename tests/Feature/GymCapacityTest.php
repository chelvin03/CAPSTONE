<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('admin creation and editing enforce capacity for browser and JSON requests', function ($attendees) {
    Mail::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $facility = Facility::create(['facility_name' => 'Capacity Gym', 'capacity' => 2000, 'status' => 'available']);
    $data = ['facility_id' => $facility->id, 'reservation_type' => 'community', 'event_name' => 'Capacity Event',
        'purpose' => 'Sports', 'contact_person' => 'Capacity Tester', 'contact_number' => '09123456789',
        'expected_attendees' => 1850, 'reservation_date' => now()->addDays(5)->toDateString(),
        'start_time' => '09:00', 'end_time' => '11:00'];
    $this->actingAs($admin)->post(route('admin.reservations.store'), array_replace($data, ['expected_attendees' => $attendees]))
        ->assertSessionHasErrors('expected_attendees');
    $this->assertDatabaseCount('reservations', 0);
    $reservation = Reservation::create($data + ['reference_number' => 'CAPACITY-TEST', 'status' => 'new']);
    $this->putJson(route('admin.reservations.update', $reservation), array_replace($data, ['expected_attendees' => $attendees]))
        ->assertUnprocessable()->assertJsonValidationErrors('expected_attendees');
    expect($reservation->fresh()->expected_attendees)->toBe(1850);
    Mail::assertNothingSent();
})->with([2001, 3000, 0, -1, '1.5']);

test('admin can save exactly 2000 and views use 2000 for attendance capacity', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $facility = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 200, 'status' => 'available']);
    $data = ['facility_id' => $facility->id, 'reservation_type' => 'community', 'event_name' => 'Capacity Event',
        'purpose' => 'Sports', 'contact_person' => 'Capacity Tester', 'contact_number' => '09123456789',
        'expected_attendees' => 2000, 'reservation_date' => now()->addDays(5)->toDateString(),
        'start_time' => '09:00', 'end_time' => '11:00'];
    $this->actingAs($admin)->post(route('admin.reservations.store'), $data)->assertSessionHasNoErrors();
    $reservation = Reservation::sole();
    $this->put(route('admin.reservations.update', $reservation), array_replace($data, ['expected_attendees' => 1850]))->assertSessionHasNoErrors();
    $this->get(route('admin.reservations.show', $reservation))->assertOk()->assertSee('Gym Capacity: 2,000')->assertSee('Capacity Usage: 92.5%');
    $reservation->update(['status' => 'approved']);
    $this->get(route('admin.dashboard', ['year' => 'all']))->assertOk()
        ->assertViewHas('gymCapacity', 2000)->assertViewHas('averageAttendanceCapacityUsage', 92.5);
});

test('model rejects decimals before integer casting and blocks oversized model writes', function ($value) {
    $reservation = new Reservation(['expected_attendees' => $value]);
    expect(fn () => $reservation->save())->toThrow(\Illuminate\Validation\ValidationException::class);
})->with(['1.5', 2001, 0]);
