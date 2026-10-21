<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\DashboardTrendService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake(); Storage::fake('local');
    config(['gym.reservation.opening_time' => '07:00', 'gym.reservation.closing_time' => '24:00']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->date = now()->addDays(5)->toDateString();
    $this->payload = ['facility_id' => $this->gym->id, 'reservation_type' => 'internal', 'event_name' => 'Evening Event',
        'purpose' => 'Sports', 'contact_person' => 'Requestor', 'contact_email' => 'requestor@example.com',
        'contact_number' => '09123456789', 'expected_attendees' => 30, 'reservation_date' => $this->date,
        'start_time' => '23:00', 'end_time' => '24:00', 'agreement' => 1, 'agreement_accepted' => 1,
        'permit' => UploadedFile::fake()->create('letter.pdf', 20, 'application/pdf')];
    $this->proof = ['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]];
});

test('public bookings support midnight end and reject invalid hours', function () {
    $this->withSession($this->proof)->post(route('reservation.store'), $this->payload)->assertSessionHasNoErrors();
    $booking = Reservation::sole();
    expect(substr($booking->end_time, 0, 5))->toBe('24:00');
    $response = $this->getJson(route('reservation.availability', ['month' => substr($this->date, 0, 7)]))->assertOk();
    $response->assertJsonPath('closing_time', '24:00');
    $slots = $response->json('days.'.$this->date.'.slots');
    expect($slots)->toHaveCount(1)->and($slots[0]['end_time'])->toBe('22:30')->and($slots[0]['available'])->toBeTrue();
    $this->withSession($this->proof)->post(route('reservation.store'), [...$this->payload, 'start_time' => '06:59'])->assertSessionHasErrors('start_time');
    foreach (['24:01', '25:00', '00:00', '22:59'] as $end) {
        $this->withSession($this->proof)->post(route('reservation.store'), [...$this->payload, 'end_time' => $end])->assertSessionHasErrors('end_time');
    }
});

test('monthly summary shows actual approved and rejected percentages and zero months', function () {
    $year = now()->year;
    foreach (['approved', 'completed', 'rejected', 'new'] as $status) {
        Reservation::create([...collect($this->payload)->except(['permit', 'agreement'])->all(),
            'reference_number' => 'MONTH-'.$status, 'reservation_date' => $year.'-01-10', 'status' => $status]);
    }
    $data = app(DashboardTrendService::class)->summarize(['date_from' => $year.'-01-01', 'date_to' => $year.'-12-31', 'grouping' => 'monthly'], app(DashboardAnalyticsService::class));
    $january = $data['dashboardMonthlyOutcomes'][0];
    expect($january['total'])->toBe(4)->and($january['approved_percent'])->toBe(50.0)->and($january['disapproved_percent'])->toBe(25.0);
    expect($data['dashboardMonthlyOutcomes'][1]['total'])->toBe(0)->and($data['dashboardMonthlyOutcomes'][1]['approved_percent'])->toBe(0);
});

test('admin availability and utilization accept midnight operating hours', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.schedule.index'))->assertOk()->assertViewHas('hours', fn ($hours) => $hours['end'] === 86400);
    $this->get(route('admin.dashboard', ['year' => now()->year]))->assertOk()->assertViewHas('operatingHours', fn ($hours) => $hours['end'] === 86400)->assertSee('View Monthly Summary');
});


test('public calendar and booking accept seven am opening and midnight closing', function () {
    $this->getJson(route('reservation.availability', ['month' => substr($this->date, 0, 7)]))
        ->assertOk()->assertJsonPath('opening_time', '07:00')->assertJsonPath('closing_time', '24:00')
        ->assertJsonPath('days.'.$this->date.'.slots.0.start_time', '07:00')
        ->assertJsonPath('days.'.$this->date.'.slots.0.end_time', '24:00');
    $this->withSession($this->proof)->post(route('reservation.store'), [...$this->payload, 'start_time' => '06:59'])
        ->assertSessionHasErrors('start_time');
    $this->withSession($this->proof)->post(route('reservation.store'), [...$this->payload, 'start_time' => '07:00'])
        ->assertSessionHasNoErrors();
    expect(substr(Reservation::sole()->start_time, 0, 5))->toBe('07:00');
});

 test('public reservations reject odd minutes and durations below one hour', function () {
    foreach ([['11:59', '14:00'], ['12:00', '12:01'], ['12:00', '12:29'], ['12:00', '12:30'], ['12:00', '12:59']] as [$start, $end]) {
        $this->withSession($this->proof)->post(route('reservation.store'), [...$this->payload, 'start_time' => $start, 'end_time' => $end])->assertSessionHasErrors();
    }
    $this->withSession($this->proof)->post(route('reservation.store'), [...$this->payload, 'start_time' => '12:00', 'end_time' => '13:00'])->assertSessionHasNoErrors();
});

 test('cleanup blocks the next booking until exactly thirty minutes after the event', function () {
    $this->withSession($this->proof)->post(route('reservation.store'), [...$this->payload, 'start_time' => '12:00', 'end_time' => '14:00'])->assertSessionHasNoErrors();
    $this->withSession($this->proof)->post(route('reservation.store'), [...$this->payload, 'start_time' => '14:00', 'end_time' => '15:00'])->assertSessionHasErrors('reservation_date');
    $this->withSession($this->proof)->post(route('reservation.store'), [...$this->payload, 'start_time' => '14:30', 'end_time' => '15:30'])->assertSessionHasNoErrors();
});
