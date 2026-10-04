<?php

use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('dashboard reporting presets select calendar ranges and clear other filters', function (string $period, string $from, string $to, string $grouping) {
    Carbon::setTestNow('2026-09-10 12:00:00');
    try {
        $admin = User::factory()->create(['role' => 'admin']);
        $gym = Facility::create(['facility_name' => 'Preset Gym', 'capacity' => 200, 'status' => 'available']);
        analyticsReservation($gym);
        analyticsReservation($gym, ['reservation_date' => '2026-09-11']);
        analyticsReservation($gym, ['reservation_date' => '2026-01-01']);
        analyticsReservation($gym, ['reservation_date' => '2025-12-31']);

        $this->actingAs($admin)->get(route('admin.dashboard', ['period' => $period, 'status' => 'cancelled']))
            ->assertOk()
            ->assertViewHas('filters', ['date_from' => $from, 'date_to' => $to, 'grouping' => $grouping])
            ->assertViewHas('selectedPeriod', $period)
            ->assertViewHas('totalReservations', match ($period) { 'today' => 1, 'month' => 2, 'year' => 3 })
            ->assertSee('Gymnasium Business Intelligence')->assertSee('Apply Filters');
    } finally {
        Carbon::setTestNow();
    }
})->with([
    ['today', '2026-09-10', '2026-09-10', 'daily'],
    ['month', '2026-09-01', '2026-09-30', 'daily'],
    ['year', '2026-01-01', '2026-12-31', 'monthly'],
]);

test('admin dashboard statistics reflect database records', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $facility = Facility::create([
        'facility_name' => 'Dashboard Gym',
        'capacity' => 200,
        'status' => 'available',
    ]);

    foreach (['new', 'approved', 'approved', 'cancelled', 'completed'] as $index => $status) {
        Reservation::create([
            'reference_number' => 'DASH-'.$index,
            'user_id' => $admin->id,
            'facility_id' => $facility->id,
            'reservation_type' => 'school',
            'event_name' => 'Dashboard Event '.$index,
            'purpose' => 'Dashboard calculation test',
            'contact_person' => 'Test Requestor '.$index,
            'contact_number' => '09123456789',
            'expected_attendees' => 20,
            'reservation_date' => now()->startOfMonth()->addDays($index + 1)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
            'status' => $status,
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('totalReservations', 5)
        ->assertViewHas('pendingReservations', 1)
        ->assertViewHas('approvedReservations', 2)
        ->assertViewHas('cancelledReservations', 1)
        ->assertViewHas('completedEvents', 1)
        ->assertDontSee('Announcements')
        ->assertSee('DASH-4')
        ->assertDontSee(route('admin.reservations.create'), false)
        ->assertSee(route('admin.reservations.index'), false);
});

function analyticsReservation($facility, array $attributes = []): Reservation
{
    return Reservation::unguarded(fn () => Reservation::create(array_merge([
        'reference_number' => 'BI-'.Str::random(10),
        'facility_id' => $facility->id,
        'reservation_type' => 'school',
        'event_type' => 'Sports',
        'event_name' => 'Analytics event',
        'purpose' => 'Analytics verification',
        'contact_person' => 'Test Requestor',
        'contact_number' => '09123456789',
        'expected_attendees' => 20,
        'reservation_date' => '2026-09-10',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'status' => 'approved',
    ], $attributes)));
}

test('analytics filters reconcile charts details comparisons and export', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Main Gym', 'capacity' => 200, 'status' => 'available']);
    $other = Facility::create(['facility_name' => 'Other Gym', 'capacity' => 200, 'status' => 'available']);
    analyticsReservation($gym, ['reference_number' => 'BI-INCLUDED', 'event_name' => '=SUM(1,2)']);
    analyticsReservation($gym, ['status' => 'cancelled']);
    analyticsReservation($gym, ['status' => 'new', 'created_at' => '2026-09-01']);
    analyticsReservation($gym, ['reservation_date' => '2026-08-15']);
    analyticsReservation($gym, ['reservation_type' => 'external']);
    analyticsReservation($other);
    $filters = ['date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'facility_id' => $gym->id, 'requestor_type' => 'school', 'category' => 'Sports'];
    $response = $this->actingAs($admin)->get(route('admin.dashboard', $filters))->assertOk()
        ->assertViewHas('totalReservations', 3)
        ->assertViewHas('previousTotal', 1)
        ->assertViewHas('change', 200.0)
        ->assertViewHas('cancellationRate', 33.3)
        ->assertViewHas('expectedAttendees', 20)
        ->assertViewHas('pendingReservations', 1)
        ->assertViewHas('monthlyActivity', fn ($rows) => $rows->sum('count') === 3)
        ->assertViewHas('eventDemand', fn ($rows) => $rows->sum('count') === 3)
        ->assertViewHas('weekdayDemand', fn ($rows) => $rows->sum('count') === 3)
        ->assertViewHas('statusBreakdown', fn ($rows) => $rows->sum('count') === 3)
        ->assertViewHas('overviewReservations', fn ($rows) => $rows->count() === 3);
    $csv = $this->get(route('admin.dashboard', array_merge($filters, ['export' => 'csv'])))->assertOk()->assertDownload('dashboard-reservations.csv')->streamedContent();
    expect($csv)->toContain('BI-INCLUDED')->toContain("'=Sum(1,2)")->not->toContain('Other Gym');
    expect($csv)->toContain('Summary metric')->toContain('Generated by administrator')->toContain('Reservation outcomes');
    $this->get(route('admin.dashboard', array_merge($filters, ['status' => 'approved'])))
        ->assertOk()->assertViewHas('totalReservations', 1)->assertViewHas('cancelledReservations', 0)
        ->assertViewHas('overviewReservations', fn ($rows) => $rows->count() === 1);
});

test('dashboard defaults to the current calendar year and handles empty data', function () {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
        ->assertViewHas('filters', ['year' => '2026', 'date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'grouping' => 'monthly'])
        ->assertViewHas('trendActivity', fn ($rows) => $rows->count() === 12)
        ->assertViewHas('totalReservations', 0)->assertViewHas('cancellationRate', null)
        ->assertViewHas('change', null)->assertSee('No reservations match these filters');
});

test('dashboard validates ranges and restricts analytics and export to admins', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->from(route('admin.dashboard'))
        ->get(route('admin.dashboard', ['date_from' => '2026-09-30', 'date_to' => '2026-09-01']))
        ->assertSessionHasErrors('date_to');
    $this->get(route('admin.dashboard', ['date_from' => '2020-01-01', 'date_to' => '2026-09-01']))->assertSessionHasErrors('date_to');
    $this->get(route('admin.dashboard', ['status' => 'invalid']))->assertSessionHasErrors('status');
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('admin.dashboard', ['export' => 'csv']))->assertForbidden();
});

test('analytics includes date boundaries and exports all pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Boundary Gym', 'capacity' => 100, 'status' => 'available']);
    foreach (range(1, 11) as $i) {
        analyticsReservation($gym, ['reference_number' => 'PAGE-'.$i, 'reservation_date' => $i === 1 ? '2026-09-01' : '2026-09-30']);
    }
    analyticsReservation($gym, ['reference_number' => 'OUTSIDE', 'reservation_date' => '2026-10-01']);
    $filters = ['date_from' => '2026-09-01', 'date_to' => '2026-09-30'];
    $this->actingAs($admin)->get(route('admin.dashboard', $filters))->assertOk()
        ->assertViewHas('totalReservations', 11)
        ->assertViewHas('overviewReservations', fn ($rows) => $rows->count() === 5);
    $csv = $this->get(route('admin.dashboard', $filters + ['export' => 'csv']))->streamedContent();
    expect($csv)->toContain('PAGE-1')->toContain('PAGE-11')->not->toContain('OUTSIDE');
});

test('every status is counted and pending review excludes waiting and legacy pending', function () {
    $this->travelTo(Carbon::parse('2026-09-28 12:00:00'));
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Main Gym', 'capacity' => 100, 'status' => 'available']);
    foreach (['new', 'validated', 'approved', 'rejected', 'waiting_list', 'cancelled', 'completed', 'pending'] as $status) {
        analyticsReservation($gym, ['status' => $status, 'created_at' => now()->subDays(7)]);
    }
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
        ->assertViewHas('totalReservations', 8)->assertViewHas('pendingReservations', 2)
        ->assertViewHas('oldPending', 2)->assertViewHas('cancellationRate', 12.5)
        ->assertViewHas('waitingCount', 1)
        ->assertViewHas('statusBreakdown', fn ($rows) => $rows->count() === 8 && $rows->every(fn ($row) => $row['count'] === 1 && $row['percentage'] === 12.5));
});

test('overdue uses submission age with an inclusive seven day boundary', function () {
    $this->travelTo(Carbon::parse('2026-09-28 12:00:00'));
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Main Gym', 'capacity' => 100, 'status' => 'available']);
    analyticsReservation($gym, ['status' => 'new', 'created_at' => now()->subDays(7)]);
    analyticsReservation($gym, ['status' => 'validated', 'created_at' => now()->subDays(7)->subSecond()]);
    analyticsReservation($gym, ['status' => 'new', 'created_at' => now()->subDays(7)->addSecond()]);
    analyticsReservation($gym, ['status' => 'waiting_list', 'created_at' => now()->subDays(30)]);
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertViewHas('pendingReservations', 3)->assertViewHas('oldPending', 2);
});

test('utilization merges overlaps clips operating hours and preserves partial hours', function () {
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '12:00']);
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Main Gym', 'capacity' => 100, 'status' => 'available']);
    foreach ([['07:30', '08:30'], ['08:15', '09:15'], ['08:20', '08:40'], ['11:45', '13:00']] as [$start, $end]) {
        analyticsReservation($gym, ['start_time' => $start, 'end_time' => $end]);
    }
    analyticsReservation($gym, ['start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed']);
    analyticsReservation($gym, ['start_time' => '10:00', 'end_time' => '11:00', 'status' => 'cancelled']);
    analyticsReservation($gym, ['start_time' => '11:00', 'end_time' => '10:00']);
    analyticsReservation($gym, ['start_time' => '10:00', 'end_time' => '10:00']);
    $this->actingAs($admin)->get(route('admin.dashboard', ['date_from' => '2026-09-10', 'date_to' => '2026-09-10']))
        ->assertOk()->assertViewHas('utilizedHours', 1.5)->assertViewHas('availableHours', 4.0)
        ->assertViewHas('utilizationRate', 37.5)
        ->assertViewHas('timeSlots', fn ($slots) => $slots->firstWhere('label', '08:00–09:00')['count'] === 3)
        ->assertViewHas('peakTimeSlot', fn ($peak) => $peak['count'] === 3);
});

test('utilization keeps different facilities separate and handles missing operating hours', function () {
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '10:00']);
    $admin = User::factory()->create(['role' => 'admin']);
    foreach (['Gym A', 'Gym B'] as $name) {
        $gym = Facility::create(['facility_name' => $name, 'capacity' => 100, 'status' => 'available']);
        analyticsReservation($gym);
    }
    $url = route('admin.dashboard', ['date_from' => '2026-09-10', 'date_to' => '2026-09-10']);
    $this->actingAs($admin)->get($url)->assertOk()->assertViewHas('utilizedHours', 2.0)->assertViewHas('availableHours', 4.0)->assertViewHas('utilizationRate', 50.0);
    config(['gym.reservation.opening_time' => null]);
    $this->get($url)->assertOk()->assertViewHas('utilizationRate', null)->assertViewHas('availableHours', null);
});

test('first final decision uses history and timestamp fallbacks without including missing decisions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Main Gym', 'capacity' => 100, 'status' => 'available']);
    $reservation = analyticsReservation($gym, ['created_at' => '2026-09-01 08:00:00', 'approved_at' => '2026-09-01 14:00:00', 'cancelled_at' => '2026-09-02 08:00:00']);
    foreach (['validated' => '2026-09-01 09:00:00', 'approved' => '2026-09-01 10:00:00', 'cancelled' => '2026-09-02 08:00:00'] as $status => $at) {
        DB::table('reservation_status_histories')->insert(['reservation_id' => $reservation->id, 'new_status' => $status, 'created_at' => $at, 'updated_at' => $at]);
    }
    analyticsReservation($gym, ['created_at' => '2026-09-01 08:00:00', 'rejected_at' => '2026-09-01 12:00:00', 'status' => 'rejected']);
    analyticsReservation($gym, ['created_at' => '2026-09-01 08:00:00', 'approved_at' => '2026-08-31 08:00:00']);
    analyticsReservation($gym);
    $this->actingAs($admin)->get(route('admin.dashboard', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']))
        ->assertOk()->assertViewHas('processingHours', 3.0)->assertViewHas('processingCount', 2);
});

test('supporting analytics use the same filters and do not disclose personal data', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Main Gym', 'capacity' => 100, 'status' => 'available']);
    $equipment = Equipment::create(['equipment_name' => 'Chairs', 'total_quantity' => 100, 'unit' => 'pcs', 'status' => 'available']);
    $included = analyticsReservation($gym, ['event_name' => 'SCHOOL ASSEMBLY', 'reservation_type' => 'student', 'status' => 'cancelled', 'cancellation_reason' => 'Weather, contact private-person@example.test', 'contact_email' => 'private-person@example.test', 'contact_number' => '09998887776']);
    $excluded = analyticsReservation($gym, ['reservation_type' => 'government', 'status' => 'completed']);
    foreach ([$included, $excluded] as $reservation) {
        $reservation->equipment()->attach($equipment, ['quantity_requested' => 10, 'quantity_approved' => 5]);
        DB::table('feedback')->insert(['reservation_id' => $reservation->id, 'user_id' => $admin->id, 'rating' => $reservation->id === $included->id ? 4 : 1, 'comments' => 'PRIVATE FEEDBACK']);
        foreach (['waiting_list' => '2026-09-01 08:00:00', 'approved' => '2026-09-02 08:00:00'] as $status => $at) {
            DB::table('reservation_status_histories')->insert(['reservation_id' => $reservation->id, 'new_status' => $status, 'created_at' => $at]);
        }
    }
    $filters = ['date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'requestor_type' => 'student', 'category' => 'Sports', 'status' => 'cancelled'];
    $this->actingAs($admin)->get(route('admin.dashboard', $filters))->assertOk()
        ->assertViewHas('totalReservations', 1)->assertViewHas('feedbackCount', 1)->assertViewHas('feedbackAverage', 4.0)
        ->assertViewHas('equipmentDemand', fn ($rows) => $rows->first()->allocated == 5 && $rows->first()->bookings == 1)
        ->assertViewHas('waitingEver', 1)->assertViewHas('waitingPromoted', 1)->assertViewHas('waitingConversion', 100.0)
        ->assertViewHas('cancellationReasons', fn ($rows) => $rows->first()->reason_group === 'Weather' && $rows->first()->total == 1)
        ->assertViewHas('utilizedHours', 0.0)->assertViewHas('completedEvents', 0)
        ->assertSee('School Assembly')->assertDontSee('private-person@example.test')->assertDontSee('09998887776')->assertDontSee('PRIVATE FEEDBACK');
    expect($included->fresh()->event_name)->toBe('SCHOOL ASSEMBLY');
    $csv = $this->get(route('admin.dashboard', $filters + ['export' => 'csv']))->assertOk()->streamedContent();
    expect($csv)->toContain($included->reference_number)->not->toContain($excluded->reference_number)
        ->not->toContain('private-person@example.test')->not->toContain('09998887776')->not->toContain('PRIVATE FEEDBACK');
    expect(AuditLog::where('action', 'analytics.export')->count())->toBe(1);
});

test('guest and requestor cannot access internal dashboard or exports', function () {
    foreach ([[], ['export' => 'csv']] as $parameters) {
        $this->get(route('admin.dashboard', $parameters))->assertRedirect(route('login'));
    }
    $requestor = User::factory()->create(['role' => 'requestor']);
    $this->actingAs($requestor)->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('admin.dashboard', ['export' => 'csv']))->assertForbidden();
});

test('waiting-list conversion requires a later approval and counts each reservation once', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Main Gym', 'capacity' => 100, 'status' => 'available']);
    foreach ([['approved', 'waiting_list'], ['waiting_list', 'approved', 'approved']] as $sequence) {
        $reservation = analyticsReservation($gym);
        foreach ($sequence as $status) {
            DB::table('reservation_status_histories')->insert(['reservation_id' => $reservation->id, 'new_status' => $status, 'created_at' => '2026-09-01 08:00:00']);
        }
    }
    $this->actingAs($admin)->get(route('admin.dashboard', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']))
        ->assertOk()->assertViewHas('waitingEver', 2)->assertViewHas('waitingPromoted', 1)->assertViewHas('waitingConversion', 50.0);
});

test('invalid dates event types and requestor groups display validation errors', function (array $filters, string $error) {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.dashboard', $filters))->assertRedirect(route('admin.dashboard'))->assertSessionHasErrors($error);
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('role="alert"', false);
})->with([
    [['date_from' => '2026-02-30'], 'date_from'],
    [['date_to' => 'bad-date'], 'date_to'],
    [['category' => 'nonexistent'], 'category'],
    [['requestor_type' => 'nonexistent'], 'requestor_type'],
    [['facility_id' => 99999], 'facility_id'],
    [['grouping' => 'predictive'], 'grouping'],
]);

test('view all pagination retains dashboard filters and keeps the existing listing workflow', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Main Gym', 'capacity' => 100, 'status' => 'available']);
    foreach (range(1, 12) as $i) {
        analyticsReservation($gym, ['reference_number' => 'FILTER-PAGE-'.$i]);
    }
    $filters = ['date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'category' => 'Sports', 'requestor_type' => 'school', 'status' => 'approved', 'facility_id' => $gym->id];
    $this->actingAs($admin)->get(route('admin.reservations.index', $filters))->assertOk()
        ->assertViewHas('reservations', function ($rows) use ($filters) {
            parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $parameters);

            return array_diff_assoc($filters, $parameters) === [] && $parameters['page'] === '2';
        });
    $this->get(route('admin.reservations.index', $filters + ['page' => 2]))->assertOk()
        ->assertViewHas('reservations', fn ($rows) => $rows->count() === 2 && $rows->total() === 12);
});

test('all trend groupings include zero buckets and clipped boundary labels', function (string $grouping, int $count) {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Main Gym', 'capacity' => 100, 'status' => 'available']);
    analyticsReservation($gym, ['reservation_date' => '2026-09-15', 'created_at' => '2020-01-01']);
    $this->actingAs($admin)->get(route('admin.dashboard', ['date_from' => '2026-09-15', 'date_to' => '2026-10-02', 'grouping' => $grouping]))
        ->assertOk()->assertViewHas('totalReservations', 1)
        ->assertViewHas('trendActivity', fn ($rows) => $rows->count() === $count && $rows->sum('count') === 1 && ($grouping === 'daily' || $rows->first()['partial']))
        ->assertViewHas('monthlyActivity', fn ($rows) => $rows->first()['partial'] && $rows->last()['partial'] && $rows->last()['count'] === 0);
})->with([['daily', 18], ['weekly', 3], ['monthly', 2], ['quarterly', 2], ['annual', 1]]);

test('calendar filters reconcile all metrics status chart overview and view all', function () {
    $this->travelTo(Carbon::parse('2026-10-04'));
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Calendar Gym', 'capacity' => 100, 'status' => 'available']);
    foreach (['new', 'validated', 'approved', 'rejected', 'waiting_list', 'cancelled'] as $status) {
        analyticsReservation($gym, ['status' => $status, 'reservation_date' => '2026-02-28']);
    }
    analyticsReservation($gym, ['reservation_date' => '2026-03-01']);
    analyticsReservation($gym, ['reservation_date' => '2025-02-28']);
    analyticsReservation($gym, ['event_type' => 'Assembly', 'reservation_date' => '2026-02-01']);
    $filters = ['year' => '2026', 'month' => '2', 'category' => 'Sports'];
    $response = $this->actingAs($admin)->get(route('admin.dashboard', $filters))->assertOk()
        ->assertViewHas('totalReservations', 6)->assertViewHas('approvedReservations', 1)
        ->assertViewHas('pendingReservations', 2)->assertViewHas('mostCommonEventType', 'Sports')
        ->assertViewHas('overviewReservations', fn ($rows) => $rows->count() === 5)
        ->assertViewHas('statusBreakdown', fn ($rows) => $rows->sum('count') === 6 && $rows->where('count', 1)->count() === 6)
        ->assertViewHas('filters', fn ($data) => $data['date_from'] === '2026-02-01' && $data['date_to'] === '2026-02-28');
    $this->get(route('admin.dashboard', $filters + ['status' => 'approved']))->assertOk()
        ->assertViewHas('totalReservations', 1)->assertViewHas('pendingReservations', 0);
    $listingFilters = \Illuminate\Support\Arr::except($response->viewData('filters'), ['year', 'grouping', 'period']);
    $this->get(route('admin.reservations.index', $listingFilters))->assertOk()
        ->assertViewHas('reservations', fn ($rows) => $rows->total() === 6);
});

test('all years month filter includes leap days and long range export', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Historical Gym', 'capacity' => 100, 'status' => 'available']);
    foreach (['2020-02-29', '2026-02-28', '2026-03-01'] as $date) {
        analyticsReservation($gym, ['reservation_date' => $date, 'reference_number' => 'DATE-'.$date]);
    }
    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['year' => 'all', 'month' => 2]))->assertOk()
        ->assertViewHas('totalReservations', 2)->assertViewHas('approvedReservations', 2)
        ->assertViewHas('years', fn ($years) => $years->contains(2020) && $years->contains(2026));
    $csv = $this->get(route('admin.dashboard', $response->viewData('filters') + ['export' => 'csv']))->assertOk()->streamedContent();
    expect($csv)->toContain('DATE-2020-02-29')->toContain('DATE-2026-02-28')->not->toContain('DATE-2026-03-01');
});

test('overview orders latest date then time and limits five without changing total', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Latest Gym', 'capacity' => 100, 'status' => 'available']);
    foreach (range(1, 7) as $day) {
        analyticsReservation($gym, ['reference_number' => 'LATEST-'.$day, 'reservation_date' => sprintf('2026-09-%02d', $day)]);
    }
    analyticsReservation($gym, ['reference_number' => 'LATEST-EVENING', 'reservation_date' => '2026-09-07', 'start_time' => '18:00', 'end_time' => '19:00']);
    $this->actingAs($admin)->get(route('admin.dashboard', ['year' => '2026']))->assertOk()
        ->assertViewHas('totalReservations', 8)
        ->assertViewHas('overviewReservations', fn ($rows) => $rows->pluck('reference_number')->all() === ['LATEST-EVENING', 'LATEST-7', 'LATEST-6', 'LATEST-5', 'LATEST-4']);
});

test('utilization deducts merged blackout intervals from both capacity and approved hours', function () {
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '12:00']);
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Blackout Gym', 'capacity' => 100, 'status' => 'available']);
    analyticsReservation($gym, ['start_time' => '08:00', 'end_time' => '12:00', 'setup_time' => '07:00', 'cleanup_time' => '13:00']);
    foreach ([[null, '09:00', '10:00'], [$gym->id, '09:30', '10:30']] as [$facility, $start, $end]) {
        \App\Models\ScheduleBlock::create(['facility_id' => $facility, 'starts_on' => '2026-09-10', 'ends_on' => '2026-09-10', 'start_time' => $start, 'end_time' => $end, 'title' => 'Closed', 'created_by' => $admin->id]);
    }
    $url = route('admin.dashboard', ['date_from' => '2026-09-10', 'date_to' => '2026-09-10']);
    $this->actingAs($admin)->get($url)->assertOk()->assertViewHas('availableHours', 2.5)
        ->assertViewHas('utilizedHours', 2.5)->assertViewHas('utilizationRate', 100.0);
    \App\Models\ScheduleBlock::create(['starts_on' => '2026-09-10', 'ends_on' => '2026-09-10', 'title' => 'Full day closed', 'created_by' => $admin->id]);
    $this->get($url)->assertOk()->assertViewHas('availableHours', 0.0)->assertViewHas('utilizedHours', 0.0)
        ->assertViewHas('utilizationRate', null)->assertSee('No bookable hours in this period');
});

test('utilization capacity matches selected month across years and facility blackouts', function () {
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '10:00']);
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Gym A', 'capacity' => 100, 'status' => 'available']);
    Facility::create(['facility_name' => 'Gym B', 'capacity' => 100, 'status' => 'available']);
    analyticsReservation($gym, ['reservation_date' => '2024-02-29']);
    analyticsReservation($gym, ['reservation_date' => '2025-02-28']);
    \App\Models\ScheduleBlock::create(['facility_id' => $gym->id, 'starts_on' => '2024-02-29', 'ends_on' => '2024-02-29', 'title' => 'Closed', 'created_by' => $admin->id]);
    $this->actingAs($admin)->get(route('admin.dashboard', ['year' => 'all', 'month' => 2]))->assertOk()
        ->assertViewHas('availableHours', 226.0)->assertViewHas('utilizedHours', 1.0)->assertViewHas('utilizationRate', 0.4);
});

test('invalid calendar filters show errors and empty event types show N A', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    foreach ([['year' => 'garbage'], ['year' => '1900'], ['month' => 13], ['month' => 0]] as $filters) {
        $this->get(route('admin.dashboard', $filters))->assertSessionHasErrors(array_key_first($filters));
    }
    $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('mostCommonEventType', 'N/A')
        ->assertViewHas('utilizationRate', null)->assertSee('No status data for these filters.');
});
