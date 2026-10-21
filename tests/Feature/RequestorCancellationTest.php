<?php

use App\Models\Facility;
use App\Models\Reservation;

beforeEach(function () {
    \Illuminate\Support\Facades\Mail::fake();
    $this->travelTo(now()->startOfSecond());
    $gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 500, 'status' => 'available']);
    $this->booking = Reservation::create(['reference_number' => 'CANCEL-TEST', 'facility_id' => $gym->id,
        'reservation_type' => 'internal', 'event_name' => 'Practice', 'purpose' => 'Sports',
        'contact_person' => 'Requestor', 'contact_email' => 'requestor@example.com',
        'contact_number' => '09123456789', 'expected_attendees' => 100,
        'reservation_date' => now()->addDays(5)->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved']);
    $this->proof = ['public_reservation_references' => [$this->booking->reference_number]];
});

test('requestor can cancel an approved reservation with a reason and release the slot', function () {
    $this->withSession($this->proof)->get(route('reservation.track', ['reference' => $this->booking->reference_number]))
        ->assertOk()->assertSee('Cancellation reason')->assertSee('three days before');
    $this->post(route('reservation.cancel', $this->booking), ['cancellation_reason' => 'Our event was postponed.'])
        ->assertSessionHasNoErrors()->assertRedirect(route('reservation.track', ['reference' => $this->booking->reference_number]));
    expect($this->booking->refresh()->status)->toBe('cancelled')
        ->and($this->booking->cancellation_reason)->toBe('Our event was postponed.')
        ->and($this->booking->cancelled_at)->not->toBeNull()->and($this->booking->cancelled_by)->toBeNull();
    $this->assertDatabaseHas('reservation_status_histories', ['reservation_id' => $this->booking->id,
        'previous_status' => 'approved', 'new_status' => 'cancelled', 'remarks' => 'Cancelled by requestor: Our event was postponed.']);
    $html = $this->getJson(route('reservation.track', ['reference' => $this->booking->reference_number]))
        ->assertJsonPath('status', 'cancelled')->json('html');
    expect($html)->toContain('Our event was postponed.');
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ReservationCancelled::class);
    $this->getJson(route('reservation.availability', ['month' => $this->booking->reservation_date->format('Y-m')]))
        ->assertJsonPath('days.'.$this->booking->reservation_date->toDateString().'.status', 'available');
});

test('cancellation requires a nonblank reason', function ($reason) {
    $this->withSession($this->proof)->post(route('reservation.cancel', $this->booking), ['cancellation_reason' => $reason])
        ->assertSessionHasErrors('cancellation_reason');
    expect($this->booking->refresh()->status)->toBe('approved');
})->with(['', '   ', null]);

test('knowing a reference does not authorize cancellation', function () {
    $this->get(route('reservation.track', ['reference' => $this->booking->reference_number]))->assertDontSee('Cancel Reservation');
    $this->post(route('reservation.cancel', $this->booking), ['cancellation_reason' => 'Unauthorized'])
        ->assertForbidden();
    expect($this->booking->refresh()->status)->toBe('approved');
});

test('a matching verified email also authorizes cancellation', function () {
    $this->withSession(['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true,
        'expires_at' => now()->addMinutes(30)->timestamp]])
        ->post(route('reservation.cancel', $this->booking), ['cancellation_reason' => 'Plans changed.'])->assertSessionHasNoErrors();
    expect($this->booking->refresh()->status)->toBe('cancelled');
});

test('cancellation closes immediately after the three day notice deadline', function () {
    $this->travelTo($this->booking->requestorCancellationDeadline()->addSecond());
    $this->withSession($this->proof)->post(route('reservation.cancel', $this->booking), ['cancellation_reason' => 'Too late'])
        ->assertSessionHasErrors('cancellation_reason');
    expect($this->booking->refresh()->status)->toBe('approved');
});

test('cancellation is allowed at exactly three days before the event', function () {
    $this->travelTo($this->booking->requestorCancellationDeadline());
    $this->withSession($this->proof)->post(route('reservation.cancel', $this->booking), ['cancellation_reason' => 'Plans changed.'])
        ->assertSessionHasNoErrors();
    expect($this->booking->refresh()->status)->toBe('cancelled');
});

test('older pending submissions can still cancel with three days notice', function () {
    $this->booking->update(['status' => 'new', 'created_at' => now()->subDays(10)]);
    $this->withSession($this->proof)->post(route('reservation.cancel', $this->booking), ['cancellation_reason' => 'Plans changed.'])
        ->assertSessionHasNoErrors();
    expect($this->booking->refresh()->status)->toBe('cancelled');
});

test('terminal reservations cannot be cancelled again', function ($status) {
    $this->booking->update(['status' => $status]);
    $this->withSession($this->proof)->post(route('reservation.cancel', $this->booking), ['cancellation_reason' => 'Plans changed.'])
        ->assertSessionHasErrors('cancellation_reason');
    expect($this->booking->refresh()->status)->toBe($status);
})->with(['cancelled', 'rejected', 'completed']);

test('an event that has started cannot be cancelled', function () {
    $this->booking->update(['reservation_date' => now()->toDateString(), 'start_time' => '00:00']);
    $this->withSession($this->proof)->post(route('reservation.cancel', $this->booking), ['cancellation_reason' => 'Plans changed.'])
        ->assertSessionHasErrors('cancellation_reason');
    expect($this->booking->refresh()->status)->toBe('approved');
});
