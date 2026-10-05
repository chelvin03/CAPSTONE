<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;

function calendarReservation(Facility $facility, string $date, string $start, string $end, string $status = 'approved'): void
{
    Reservation::create(['reference_number' => 'CAL-'.uniqid(), 'facility_id' => $facility->id,
        'reservation_type' => 'student', 'event_name' => 'Sports', 'purpose' => 'Practice',
        'contact_person' => 'Requestor', 'contact_number' => '09123456789', 'expected_attendees' => 20,
        'reservation_date' => $date, 'start_time' => $start, 'end_time' => $end, 'status' => $status]);
}

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 3, 1)->startOfDay());
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '17:00']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 500, 'status' => 'available']);
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
    expect($days['2026-03-10']['status'])->toBe('partial')->and($days['2026-03-10']['label'])->toBe('Available Time')
        ->and($days['2026-03-11']['status'])->toBe('full')->and($days['2026-03-11']['label'])->toBe('Fully Booked')
        ->and($days['2026-03-12']['status'])->toBe('available')->and($days['2026-03-12']['label'])->toBe('Available')
        ->and($days['2026-03-01']['selectable'])->toBeFalse();
    expect(array_column($days['2026-03-11']['slots'], 'available'))->not->toContain(true);
    expect(array_column($days['2026-03-10']['slots'], 'available'))->toContain(true, false);
    $response->assertDontSee('Requestor')->assertDontSee('09123456789');
    $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'date' => '2026-03-10']))->assertOk()->assertJsonCount(2);
});

test('short free gaps keep dates partially booked', function () {
    calendarReservation($this->gym, '2026-03-10', '07:00', '12:00');
    calendarReservation($this->gym, '2026-03-10', '12:30', '18:00');
    $day = $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-03']))->assertOk()->json('days.2026-03-10');
    expect($day['status'])->toBe('partial');
    expect(array_values(array_filter($day['slots'], fn ($slot) => $slot['available'])))
        ->toBe([['start_time' => '12:00', 'end_time' => '12:30', 'available' => true, 'label' => 'Available']]);
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

test('verified booking page replaces the original date picker with the availability calendar', function () {
    $html = $this->withSession([
        'public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp],
        'public_reservation_details' => ['contact_email' => 'requestor@example.com', 'contact_person' => 'Requestor'],
    ])->get(route('reservation.create', ['step' => 3]))->assertOk()
        ->assertSee('Reservation Calendar / Facility Availability')
        ->assertSee('booking-slot-grid')
        ->assertDontSee('Gymnasium Operational Slots (8:00 AM - 9:00 PM)')->getContent();
    $event = strpos($html, 'aria-labelledby="event-heading"');
    $calendar = strpos($html, 'class="booking-calendar"');
    $confirmation = strpos($html, 'aria-labelledby="confirmation-heading"');
    expect($calendar)->toBeGreaterThan($event)->toBeLessThan($confirmation);
    expect(substr_count($html, 'class="booking-calendar"'))->toBe(1);
    expect($html)->toContain('if (this.step === 2) this.openBookingCalendar();');
});

test('flexible reservations enforce hours and refresh remaining time after submission', function () {
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '21:00']);
    \Illuminate\Support\Facades\Mail::fake();
    \Illuminate\Support\Facades\Storage::fake('local');
    $this->withSession(['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]]);
    $payload = ['facility_id' => $this->gym->id, 'reservation_type' => 'student', 'event_name' => 'Practice',
        'purpose' => 'Sports', 'contact_person' => 'Requestor', 'contact_email' => 'requestor@example.com',
        'contact_number' => '09123456789', 'expected_attendees' => 20, 'reservation_date' => '2026-03-10',
        'start_time' => '08:00', 'end_time' => '17:00', 'agreement' => '1',
        'permit' => \Illuminate\Http\UploadedFile::fake()->create('letter.pdf', 20, 'application/pdf')];
    $this->post(route('reservation.store'), [...$payload, 'start_time' => '07:59'])->assertSessionHasErrors('start_time');
    $this->post(route('reservation.store'), [...$payload, 'end_time' => '21:01'])->assertSessionHasErrors('start_time');
    $this->post(route('reservation.store'), $payload)->assertSessionHasNoErrors();
    $day = $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-03']))->assertOk()->json('days.2026-03-10');
    expect($day['slots'])->toBe([
        ['start_time' => '08:00', 'end_time' => '17:00', 'available' => false, 'label' => 'Reserved'],
        ['start_time' => '17:00', 'end_time' => '21:00', 'available' => true, 'label' => 'Available'],
    ]);
    $this->withSession(['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]]);
    $this->post(route('reservation.store'), [...$payload, 'start_time' => '16:00', 'end_time' => '19:00'])->assertSessionHasErrors('reservation_date');
    $this->post(route('reservation.store'), [...$payload, 'start_time' => '17:00', 'end_time' => '21:00'])->assertSessionHasNoErrors();
    $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-03']))->assertJsonPath('days.2026-03-10.status', 'full');
    expect(Reservation::whereDate('reservation_date', '2026-03-10')->count())->toBe(2);
    $this->withSession(['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]]);
    $this->post(route('reservation.store'), [...$payload, 'reservation_date' => '2026-03-11', 'start_time' => '08:00', 'end_time' => '21:00'])->assertSessionHasNoErrors();
    $this->getJson(route('reservation.availability', ['facility_id' => $this->gym->id, 'month' => '2026-03']))->assertJsonPath('days.2026-03-11.status', 'full');
});
