<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;

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
        ->assertViewHas('thisMonthReservations', 5)
        ->assertViewHas('pendingReservations', 1)
        ->assertViewHas('approvedReservations', 2)
        ->assertViewHas('approvalRate', 40)
        ->assertViewHas('cancelledReservations', 1)
        ->assertViewHas('thisMonthCancellations', 1)
        ->assertViewHas('completedEvents', 1)
        ->assertDontSee('Announcements')
        ->assertSee('DASH-4')
        ->assertSee(route('admin.reservations.create'), false)
        ->assertSee(route('admin.reservations.index'), false);
});

function analyticsReservation($facility, array $attributes = []): Reservation
{
    return Reservation::create(array_merge([
        'reference_number' => 'BI-'.\Illuminate\Support\Str::random(10),
        'facility_id' => $facility->id,
        'reservation_type' => 'school',
        'event_name' => 'Analytics event',
        'purpose' => 'Analytics verification',
        'contact_person' => 'Test Requestor',
        'contact_number' => '09123456789',
        'expected_attendees' => 20,
        'reservation_date' => '2026-09-10',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'status' => 'approved',
    ], $attributes));
}

test('analytics filters reconcile charts details comparisons and export', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'Main Gym', 'capacity' => 200, 'status' => 'available']);
    $other = Facility::create(['facility_name' => 'Other Gym', 'capacity' => 200, 'status' => 'available']);
    analyticsReservation($gym, ['reference_number' => 'BI-INCLUDED', 'event_name' => '=SUM(1,2)']);
    analyticsReservation($gym, ['status' => 'cancelled']);
    analyticsReservation($gym, ['status' => 'pending', 'created_at' => '2026-09-01']);
    analyticsReservation($gym, ['reservation_date' => '2026-08-15']);
    analyticsReservation($gym, ['reservation_type' => 'external']);
    analyticsReservation($other);
    $filters = ['date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'facility_id' => $gym->id, 'category' => 'school'];
    $response = $this->actingAs($admin)->get(route('admin.dashboard', $filters))->assertOk()
        ->assertViewHas('totalReservations', 3)
        ->assertViewHas('previousTotal', 1)
        ->assertViewHas('change', 200.0)
        ->assertViewHas('cancellationRate', 33.3)
        ->assertViewHas('expectedAttendees', 20)
        ->assertViewHas('pendingReservations', 1)
        ->assertViewHas('monthlyActivity', fn ($rows) => $rows->sum('count') === 3)
        ->assertViewHas('facilityDemand', fn ($rows) => $rows->sum('count') === 3)
        ->assertViewHas('weekdayDemand', fn ($rows) => $rows->sum('count') === 3)
        ->assertViewHas('statusBreakdown', fn ($rows) => $rows->sum('count') === 3)
        ->assertViewHas('recentReservations', fn ($rows) => $rows->total() === 3);
    $csv = $this->get(route('admin.dashboard', array_merge($filters, ['export' => 'csv'])))->assertOk()->assertDownload('dashboard-reservations.csv')->streamedContent();
    expect($csv)->toContain('BI-INCLUDED')->toContain("'=SUM(1,2)")->not->toContain('Other Gym');
    expect(count(array_filter(explode("\n", trim($csv)))))->toBe(4);
    $this->get(route('admin.dashboard', array_merge($filters, ['status' => 'approved'])))
        ->assertOk()->assertViewHas('totalReservations', 1)->assertViewHas('cancelledReservations', 0)
        ->assertViewHas('recentReservations', fn ($rows) => $rows->total() === 1);
});

test('monthly analytics has twelve unique months at month end and handles empty data', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-03-31 12:00:00'));
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
        ->assertViewHas('monthlyActivity', fn ($rows) => $rows->count() === 12 && $rows->map(fn ($row) => $row['year'].'-'.$row['label'])->unique()->count() === 12 && $rows->last()['label'] === 'Mar')
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
        ->assertViewHas('recentReservations', fn ($rows) => $rows->total() === 11 && $rows->count() === 10);
    $csv = $this->get(route('admin.dashboard', $filters + ['export' => 'csv']))->streamedContent();
    expect($csv)->toContain('PAGE-1')->toContain('PAGE-11')->not->toContain('OUTSIDE');
});
