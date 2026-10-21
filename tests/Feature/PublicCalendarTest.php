<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;

function calendarReservation(Facility $facility, string $date, string $start, string $end, string $status = 'approved'): void
{
    Reservation::create(['reference_number' => 'CAL-'.uniqid(), 'facility_id' => $facility->id,
        'reservation_type' => 'student', 'event_name' => 'Sports', 'purpose' => 'Practice',
        'contact_person' => 'Requestor', 'contact_number' => '09123456789', 'expected_attendees' => 100,
        'reservation_date' => $date, 'start_time' => $start, 'end_time' => $end, 'status' => $status]);
}

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 3, 1)->startOfDay());
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '17:00']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 500, 'status' => 'available']);
});

test('calendar supports a rolling fifteen year booking horizon', function () {
    $this->getJson(route('reservation.availability', ['month' => '2041-03']))
        ->assertOk()->assertJsonPath('days.2041-03-01.selectable', true)
        ->assertJsonPath('days.2041-03-02.selectable', false);
    $this->travelTo(now()->setDate(2026, 4, 1));
    $this->getJson(route('reservation.availability', ['month' => '2041-04']))
        ->assertOk()->assertJsonPath('days.2041-04-01.selectable', true);
});

test('calendar distinguishes partial full and empty days while retaining the date endpoint', function () {
    calendarReservation($this->gym, '2026-03-10', '08:00', '10:00');
    calendarReservation($this->gym, '2026-03-10', '15:00', '17:00', 'new');
    calendarReservation($this->gym, '2026-03-11', '08:00', '12:00');
    calendarReservation($this->gym, '2026-03-11', '11:00', '17:00');
    calendarReservation($this->gym, '2026-03-12', '08:00', '17:00', 'cancelled');
    calendarReservation($this->gym, '2026-03-12', '08:00', '17:00', 'rejected');
    $response = $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-03']))->assertOk();
    $days = $response->json('days');
    expect($days['2026-03-10']['status'])->toBe('partial')->and($days['2026-03-10']['label'])->toBe('Reserved / available times')
        ->and($days['2026-03-11']['status'])->toBe('full')->and($days['2026-03-11']['label'])->toBe('Unavailable')
        ->and($days['2026-03-12']['status'])->toBe('available')->and($days['2026-03-12']['label'])->toBe('Available')
        ->and($days['2026-03-01']['selectable'])->toBeFalse();
    expect(array_column($days['2026-03-11']['slots'], 'available'))->not->toContain(true);
    expect(array_column($days['2026-03-10']['slots'], 'available'))->toBe([true]);
    $response->assertDontSee('Requestor')->assertDontSee('09123456789');
    $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'date' => '2026-03-10']))->assertOk()->assertJsonCount(1);
});

test('cleaning buffer removes short free gaps', function () {
    calendarReservation($this->gym, '2026-03-10', '07:00', '12:00');
    calendarReservation($this->gym, '2026-03-10', '12:30', '18:00');
    $day = $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-03']))->assertOk()->json('days.2026-03-10');
    expect($day['status'])->toBe('full');
    expect(array_values(array_filter($day['slots'], fn ($slot) => $slot['available'])))
        ->toBe([]);
});

test('global blocks facility blocks and maintenance affect calendar availability', function () {
    $admin = \App\Models\User::factory()->create();
    $other = Facility::create(['facility_name' => 'Other Gym', 'capacity' => 100, 'status' => 'available']);
    ScheduleBlock::create(['created_by' => $admin->id, 'facility_id' => null, 'starts_on' => '2026-03-10', 'ends_on' => '2026-03-11', 'title' => 'Maintenance']);
    ScheduleBlock::create(['created_by' => $admin->id, 'facility_id' => $this->gym->id, 'starts_on' => '2026-03-12', 'ends_on' => '2026-03-12', 'start_time' => '10:00', 'end_time' => '12:00', 'title' => 'Cleaning']);
    ScheduleBlock::create(['created_by' => $admin->id, 'facility_id' => $other->id, 'starts_on' => '2026-03-13', 'ends_on' => '2026-03-13', 'title' => 'Other']);
    $days = $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-03']))->assertOk()->json('days');
    expect($days['2026-03-10']['status'])->toBe('full')->and($days['2026-03-11']['status'])->toBe('full')
        ->and($days['2026-03-12']['status'])->toBe('partial')->and($days['2026-03-13']['status'])->toBe('available');
    $this->gym->update(['status' => 'maintenance']);
    $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-03']))->assertOk()->assertJsonPath('days.2026-03-13.status', 'full');
    $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-13']))->assertUnprocessable();
});

test('available periods leave exactly thirty cleaning minutes on either side of a booking', function () {
    config(['gym.reservation.closing_time' => '24:00']);
    calendarReservation($this->gym, '2026-03-10', '15:00', '19:00');
    $day = $this->getJson(route('reservation.availability', ['month' => '2026-03']))
        ->assertOk()->json('days.2026-03-10');
    expect($day['slots'])->toBe([
        ['start_time' => '08:00', 'end_time' => '14:30', 'available' => true, 'label' => 'Available'],
        ['start_time' => '19:30', 'end_time' => '24:00', 'available' => true, 'label' => 'Available'],
    ]);
    $calendar = app(\App\Services\PublicCalendarService::class);
    expect($calendar->hasReservationConflict($this->gym->id, '2026-03-10', '12:00', '14:30'))->toBeFalse()
        ->and($calendar->hasReservationConflict($this->gym->id, '2026-03-10', '12:00', '14:31'))->toBeTrue()
        ->and($calendar->hasReservationConflict($this->gym->id, '2026-03-10', '19:29', '21:00'))->toBeTrue()
        ->and($calendar->hasReservationConflict($this->gym->id, '2026-03-10', '19:30', '21:00'))->toBeFalse();
});

test('verified booking page replaces the original date picker with the availability calendar', function () {
    $html = $this->withSession([
        'public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp],
        'public_reservation_details' => ['contact_email' => 'requestor@example.com', 'contact_person' => 'Requestor'],
    ])->get(route('reservation.create', ['step' => 3]))->assertOk()
        ->assertSee('Reservation Calendar / Gym Availability')
        ->assertSee('booking-slot-grid')
        ->assertDontSee('Gymnasium Operational Slots (8:00 AM - 9:00 PM)')->getContent();
    $event = strpos($html, 'aria-labelledby="event-heading"');
    $calendar = strpos($html, 'class="booking-calendar"');
    $confirmation = strpos($html, 'aria-labelledby="confirmation-heading"');
    expect($calendar)->toBeGreaterThan($event)->toBeLessThan($confirmation);
    expect(substr_count($html, 'class="booking-calendar"'))->toBe(1);
    expect($html)->toContain('if (this.step === 1) this.openBookingCalendar();');
});

test('flexible reservations enforce hours and refresh remaining time after submission', function () {
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '21:00']);
    \Illuminate\Support\Facades\Mail::fake();
    \Illuminate\Support\Facades\Storage::fake('local');
    $this->withSession(['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]]);
    $payload = ['facility_id' => $this->gym->id, 'reservation_type' => 'internal', 'event_name' => 'Practice',
        'purpose' => 'Sports', 'contact_person' => 'Requestor', 'contact_email' => 'requestor@example.com',
        'contact_number' => '09123456789', 'expected_attendees' => 100, 'reservation_date' => '2026-03-10',
        'start_time' => '08:00', 'end_time' => '17:00', 'agreement' => '1', 'agreement_accepted' => '1',
        'permit' => \Illuminate\Http\UploadedFile::fake()->create('letter.pdf', 20, 'application/pdf')];
    $this->post(route('reservation.store'), [...$payload, 'start_time' => '07:59'])->assertSessionHasErrors('start_time');
    $this->post(route('reservation.store'), [...$payload, 'end_time' => '21:01'])->assertSessionHasErrors('end_time');
    $this->post(route('reservation.store'), $payload)->assertSessionHasNoErrors();
    Reservation::latest('id')->first()->update(['status' => 'approved']);
    $day = $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-03']))->assertOk()->json('days.2026-03-10');
    expect($day['slots'])->toBe([
        ['start_time' => '17:30', 'end_time' => '21:00', 'available' => true, 'label' => 'Available'],
    ]);
    $this->withSession(['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]]);
    $this->post(route('reservation.store'), [...$payload, 'start_time' => '16:00', 'end_time' => '19:00'])->assertSessionHasErrors('reservation_date');
    $this->post(route('reservation.store'), [...$payload, 'start_time' => '17:00', 'end_time' => '21:00'])->assertSessionHasErrors('reservation_date');
    $this->post(route('reservation.store'), [...$payload, 'start_time' => '18:00', 'end_time' => '21:00'])->assertSessionHasNoErrors();
    Reservation::latest('id')->first()->update(['status' => 'approved']);
    $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-03']))->assertJsonPath('days.2026-03-10.status', 'full');
    expect(Reservation::whereDate('reservation_date', '2026-03-10')->count())->toBe(2);
    $this->withSession(['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]]);
    $this->post(route('reservation.store'), [...$payload, 'reservation_date' => '2026-03-11', 'start_time' => '08:00', 'end_time' => '21:00'])->assertSessionHasNoErrors();
    Reservation::latest('id')->first()->update(['status' => 'approved']);
    $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-03']))->assertJsonPath('days.2026-03-11.status', 'full');
});


test('reservation details precede requestor information before email verification', function () {
    $html = $this->get(route('reservation.create'))->assertOk()->getContent();
    expect(strpos($html, 'aria-labelledby="event-heading"'))->toBeLessThan(strpos($html, 'aria-labelledby="requestor-heading"'));
    expect($html)->toContain('step: 1,', '@click="continueToRequestor"', 'this.reviewReservation();')
        ->not->toContain('window.location.assign(data.redirect)');
});

test('calendar hides gaps that cannot fit a one hour booking', function () {
    $slots = app(\App\Services\PublicCalendarService::class)->slots([
        ['start_time' => '08:30', 'end_time' => '09:00'],
    ], '08:00', '09:30');
    expect(array_values(array_filter($slots, fn ($slot) => $slot['available'])))->toBe([]);
});
