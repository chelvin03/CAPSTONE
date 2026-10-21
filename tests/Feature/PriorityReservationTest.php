<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('admin priority reservation overrides a conflicting slot and notifies the affected requestor', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $facility = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 200, 'status' => 'available']);
    $affected = Reservation::create([
        'reference_number' => 'MCST-AFFECTED',
        'facility_id' => $facility->id,
        'reservation_type' => 'community',
        'event_name' => 'Community Event',
        'purpose' => 'Community use',
        'contact_person' => 'Affected User',
        'contact_email' => 'affected@example.com',
        'contact_number' => '09123456789',
        'expected_attendees' => 50,
        'reservation_date' => now()->addDay()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '11:00',
        'status' => 'approved',
    ]);

    $this->actingAs($admin)->post(route('admin.reservations.store'), [
        'facility_id' => $facility->id,
        'reservation_type' => 'government',
        'event_name' => 'Priority Government Event',
        'purpose' => 'Emergency priority use',
        'contact_person' => 'Priority Officer',
        'contact_email' => 'priority@example.com',
        'contact_number' => '09111111111',
        'expected_attendees' => 80,
        'reservation_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'priority_override' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.reservations.index'));

    expect($affected->refresh()->status)->toBe('cancelled');
    expect($affected->cancellation_reason)->toContain('priority reservation');
    Mail::assertSent(\App\Mail\ReservationCancelled::class, fn ($mail) => $mail->hasTo('affected@example.com') && $mail->reservation->id === $affected->id);
    $priority = Reservation::where('event_name', 'Priority Government Event')->firstOrFail();
    expect($priority->status)->toBe('approved')->and($priority->priority_number)->toBe(1)
        ->and($priority->reference_number)->toBe('R1-A');
    $this->assertDatabaseHas('reservation_status_histories', [
        'reservation_id' => $affected->id,
        'new_status' => 'cancelled',
    ]);
});

test('ordinary admin booking cannot use an occupied slot without priority override', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $facility = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 100, 'status' => 'available']);
    Reservation::create([
        'reference_number' => 'MCST-LOCKED', 'facility_id' => $facility->id,
        'reservation_type' => 'school', 'event_name' => 'Existing Event', 'purpose' => 'Test',
        'contact_person' => 'Existing User', 'contact_number' => '09123456789',
        'expected_attendees' => 100, 'reservation_date' => now()->addDay()->toDateString(),
        'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved',
    ]);

    $this->actingAs($admin)->post(route('admin.reservations.store'), [
        'facility_id' => $facility->id, 'reservation_type' => 'school',
        'event_name' => 'Blocked Event', 'purpose' => 'Test', 'contact_person' => 'Admin',
        'contact_number' => '09111111111', 'expected_attendees' => 100,
        'reservation_date' => now()->addDay()->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00',
    ])->assertSessionHasErrors('reservation_date');

    $this->assertDatabaseMissing('reservations', ['event_name' => 'Blocked Event']);
});
