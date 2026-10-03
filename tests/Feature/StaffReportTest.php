<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Models\AnalyticsReport;

test('staff can generate reservation analytics and export them', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $facility = Facility::create([
        'facility_name' => 'Main Gym',
        'capacity' => 200,
        'status' => 'available',
    ]);

    Reservation::create([
        'reference_number' => 'REPORT-001',
        'user_id' => $staff->id,
        'facility_id' => $facility->id,
        'reservation_type' => 'school',
        'event_name' => 'Analytics Event',
        'purpose' => 'Report testing',
        'contact_person' => 'Jamie Santos',
        'contact_number' => '09123456789',
        'expected_attendees' => 75,
        'reservation_date' => '2026-08-15',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'status' => 'approved',
    ]);

    $filters = ['date_from' => '2026-08-01', 'date_to' => '2026-08-31'];

    $this->actingAs($staff)
        ->get(route('staff.reports.index', $filters))
        ->assertOk()
        ->assertViewHas('totalReservations', 1)
        ->assertViewHas('totalAttendees', 75)
        ->assertSee('Main Gym')
        ->assertSee('Approved');

    $this->get(route('staff.reports.export', $filters))
        ->assertOk()
        ->assertDownload('reservation-analytics-'.now()->format('Y-m-d').'.csv');

    $this->post(route('staff.reports.store'), [
        ...$filters,
        'report_type' => 'monthly',
    ])->assertRedirect(route('staff.reports.index', $filters));

    $report = AnalyticsReport::firstOrFail();
    expect($report->report_type)->toBe('monthly')
        ->and($report->total_reservations)->toBe(1)
        ->and($report->total_attendees)->toBe(75);

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('Monthly')
        ->assertSee($staff->full_name)
        ->assertSee(route('admin.reports.show', $report), false);

    $this->get(route('admin.reports.show', $report))
        ->assertOk()
        ->assertSee('Main Gym')
        ->assertSee('75');
});

test('admin cannot access staff reports', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('staff.reports.index'))
        ->assertForbidden();
});
