<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\DashboardInsightService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
    config(['gym.reservation.opening_time' => '06:00', 'gym.reservation.closing_time' => '22:00']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'Insight Gym', 'capacity' => 2000, 'status' => 'available']);
    $this->booking = fn ($overrides = []) => Reservation::create(array_merge([
        'reference_number' => 'BI-'.uniqid(), 'user_id' => $this->admin->id, 'facility_id' => $this->gym->id,
        'reservation_type' => 'internal', 'event_name' => 'Sports day', 'event_type' => 'Sports', 'purpose' => 'Practice',
        'contact_person' => 'Coordinator', 'contact_number' => '09123456789', 'expected_attendees' => 50,
        'reservation_date' => '2026-09-07', 'start_time' => '07:00', 'end_time' => '09:00', 'status' => 'approved',
    ], $overrides));
    $this->scope = ['date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'grouping' => 'monthly'];
    $this->insights = fn ($filters = []) => app(DashboardInsightService::class)->summarize(array_merge($this->scope, $filters), app(DashboardAnalyticsService::class));
});

test('insight charts count scheduled dates, cancellations and approved interval overlaps', function () {
    ($this->booking)();
    ($this->booking)(['status' => 'cancelled']);
    ($this->booking)(['status' => 'rejected']);
    $data = ($this->insights)();
    expect($data['monthly'])->toHaveCount(12)->and($data['monthly'][8]['rate'])->toBe(33.3)
        ->and($data['monthly'][0]['rate'])->toBe(0)->and($data['peaks'][0]['percentage'])->toBe(100.0)
        ->and($data['heat'][0]['counts'][0])->toBe(1)->and($data['heat'][1]['counts'][0])->toBe(1)
        ->and($data['heat'][0]['month_counts'][8])->toBe(1)->and($data['heat'][0]['month_counts'][0])->toBe(0)
        ->and($data['heat'][2]['counts'][0])->toBe(0)->and($data['heatPeakDay'])->toBe('Monday');
    expect(($this->insights)(['heat_scope' => 'all'])['heat'][0]['counts'][0])->toBe(3);
    expect(($this->insights)(['status' => 'cancelled'])['monthly'][8]['rate'])->toBe(100.0);
});

test('forecast uses exactly three completed months including zeros with a past cutoff', function () {
    foreach (['2026-06-01', '2026-07-01', '2026-09-01', '2026-09-02', '2026-10-01', '2027-01-01'] as $date) ($this->booking)(['reservation_date' => $date]);
    $data = ($this->insights)();
    expect(array_column($data['history'], 'count'))->toBe([1, 0, 2])->and($data['estimate'])->toBe(1.0)
        ->and($data['forecast'][0]['label'])->toBe('Nov 2026');
    $past = ($this->insights)(['date_to' => '2026-09-15']);
    expect(array_column($past['history'], 'count'))->toBe([1, 1, 0])->and($past['estimate'])->toBe(0.7)
        ->and($past['forecast'][0]['label'])->toBe('Oct 2026');
});

test('completeness follows public and admin requirements and exposes reference only', function () {
    ($this->booking)(['event_type' => null]);
    $public = ($this->booking)(['user_id' => null, 'contact_email' => 'coordinator@example.test']);
    $data = ($this->insights)();
    expect($data['completeCount'])->toBe(1)->and($data['completenessRate'])->toBe(50.0)
        ->and($data['issues']['Missing required approval letter'][0])->toBe(['id' => $public->id, 'reference' => $public->reference_number]);
    $public->documents()->create(['document_type' => 'permit', 'original_filename' => 'letter.pdf', 'stored_filename' => 'letter.pdf', 'file_path' => 'permits/letter.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100]);
    expect(($this->insights)()['completenessRate'])->toBe(100.0);
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Reservations Requiring Attention')->assertSee('name="category"', false);
});

test('empty insights and filters are safe and staff cannot access BI', function () {
    $data = ($this->insights)();
    expect($data['completenessRate'])->toBe(0)->and($data['estimate'])->toBeNull()->and($data['issues'])->toBe([]);
    ($this->booking)();
    expect(($this->insights)(['month' => 8])['monthly']->sum('count'))->toBe(0);
    $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('admin.dashboard'))->assertForbidden();
});

test('legacy invalid attendance and missing required contact are flagged without changing status', function () {
    $booking = ($this->booking)();
    // Simulate legacy/imported data without bypassing production model validation in the application.
    Illuminate\Support\Facades\DB::table('reservations')->where('id', $booking->id)->update(['expected_attendees' => 2001, 'contact_number' => '']);
    $data = ($this->insights)();
    expect($data['incompleteCount'])->toBe(1)->and($data['issues'])->toHaveKeys(['Missing contact information', 'Attendees exceed gym capacity (2,000)'])
        ->and($booking->fresh()->status)->toBe('approved');
    Illuminate\Support\Facades\DB::table('reservations')->where('id', $booking->id)->update(['expected_attendees' => 0]);
    expect(($this->insights)()['issues'])->toHaveKey('Invalid attendee count');
    config(['gym.reservation.opening_time' => null, 'gym.reservation.closing_time' => null]);
    expect(($this->insights)()['heatUsesOperatingHours'])->toBeFalse();
});
