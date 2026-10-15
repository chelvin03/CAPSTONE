<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;

beforeEach(function () {
    $facility = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 500, 'status' => 'available']);
    $this->reservation = Reservation::create([
        'reference_number' => 'MCST-GYM-2026-00125', 'facility_id' => $facility->id,
        'reservation_type' => 'student', 'event_name' => 'Sports Day', 'event_type' => 'Basketball',
        'purpose' => 'Practice', 'contact_person' => 'Requestor Name', 'contact_number' => '09123456789',
        'expected_attendees' => 40, 'reservation_date' => now()->addDays(3)->toDateString(),
        'start_time' => '08:00', 'end_time' => '17:00', 'status' => 'new',
    ]);
});

test('submission confirmation immediately shows tracking and complete reservation details', function () {
    $this->get(route('reservation.success', $this->reservation->reference_number))->assertOk()
        ->assertSee('Reservation Request Submitted Successfully')->assertSee('Reservation Tracking')
        ->assertSee('Copy Reference Number')->assertSee('Pending')->assertDontSee('tracking-timeline')->assertDontSee('Validated')->assertDontSee('Completed')
        ->assertSee('waiting for administrator review')->assertSee('Requestor Name')->assertSee('Sports Day')
        ->assertSee('Basketball')->assertSee('Date Submitted')->assertSee('Number of Participants')
        ->assertSee('8:00 AM')->assertSee('5:00 PM')->assertSee('View Reservation')->assertDontSee('Back to Home')->assertDontSee('Make Another Reservation')
        ->assertSee(route('home'), false);
});

test('tracking refresh reflects every supported status from the saved reservation', function ($status, $message) {
    $this->reservation->update(['status' => $status]);
    $response = $this->getJson(route('reservation.track', ['reference' => $this->reservation->reference_number]))
        ->assertOk()->assertJsonPath('status', $status)->assertHeader('Cache-Control', 'no-store, private');
    expect($response->json('html'))->toContain($message);
})->with([
    ['new', 'waiting for administrator review'], ['validated', 'reviewed and validated'],
    ['approved', 'schedule is confirmed'], ['waiting_list', 'placed on the waiting list'],
    ['rejected', 'rejected by the administrator'], ['cancelled', 'has been cancelled'], ['completed', 'has been completed'],
]);

test('admin approval changes the public tracking response without a new request', function () {
    $before = $this->getJson(route('reservation.track', ['reference' => $this->reservation->reference_number]))->assertOk()->json('version');
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->patch(route('admin.reservations.approve', $this->reservation))->assertRedirect()->assertSessionHasNoErrors();
    $response = $this->getJson(route('reservation.track', ['reference' => $this->reservation->reference_number]))
        ->assertOk()->assertJsonPath('status', 'approved');
    expect($response->json('version'))->not->toBe($before);
    expect(Reservation::count())->toBe(1);
});

test('cancelled tracking displays the saved reason and invites the requestor to reschedule', function () {
    $this->reservation->update(['status' => 'cancelled', 'cancellation_reason' => 'Emergency roof repairs.']);
    $response = $this->getJson(route('reservation.track', ['reference' => $this->reservation->reference_number]))
        ->assertOk()->assertJsonPath('status', 'cancelled');
    expect($response->json('html'))->toContain('Cancellation reason:', 'Emergency roof repairs.',
        'Please reschedule', route('reservation.create'));
});

test('reservation history stays hidden even with references saved in this browser', function () {
    $this->withSession(['public_reservation_references' => [$this->reservation->reference_number]])
        ->get(route('reservation.track'))->assertOk()->assertDontSee('My Reservation History')->assertDontSee('Sports Day')->assertDontSee($this->reservation->reference_number);
    $this->withSession(['public_reservation_references' => []])->get(route('reservation.track'))
        ->assertOk()->assertDontSee('Sports Day');
    $this->getJson(route('reservation.track', ['reference' => 'UNKNOWN']))->assertNotFound();
});


test('tracking shows a single status with its matching color', function ($status, $label, $color) {
    $this->reservation->update(['status' => $status]);
    $html = $this->getJson(route('reservation.track', ['reference' => $this->reservation->reference_number]))
        ->assertOk()->json('html');
    expect($html)->toContain('tracking-status tracking-status-'.$color.'">'.$label.'</span>')
        ->not->toContain('tracking-timeline', 'Reservation progress');
})->with([
    ['new', 'Pending', 'pending'],
    ['approved', 'Approved', 'approved'],
    ['cancelled', 'Cancelled', 'declined'],
    ['rejected', 'Rejected', 'declined'],
]);
