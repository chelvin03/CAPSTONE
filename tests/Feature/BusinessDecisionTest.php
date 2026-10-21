<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;

function decisionBooking(Facility $gym, string $date, string $status = 'approved'): void
{
    Reservation::create(['reference_number' => 'DEC-'.uniqid(), 'facility_id' => $gym->id,
        'reservation_type' => 'internal', 'event_name' => 'Sample Event', 'event_type' => 'Sports',
        'purpose' => 'Practice', 'contact_person' => 'Private Person', 'contact_number' => '09123456789',
        'expected_attendees' => 50, 'reservation_date' => $date, 'start_time' => '09:00', 'end_time' => '11:00', 'status' => $status]);
}

test('existing dashboard shows decision support with honest empty and zero baseline states', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.dashboard'))->assertOk()->assertSee('Insights and suggested actions')
        ->assertSee('Limited sample')->assertSee('no requests recorded in the previous period')
        ->assertSee('No booking patterns can be identified')->assertViewHas('decisionChange', null);
});

test('month comparisons query the preceding equal length period without retaining current month filter', function () {
    $gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    decisionBooking($gym, '2026-09-15');
    decisionBooking($gym, '2026-10-15');
    decisionBooking($gym, '2026-10-16', 'cancelled');
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.dashboard', ['year' => 2026, 'month' => 10]))->assertOk()
        ->assertViewHas('decisionPreviousTotal', 1)->assertViewHas('decisionChange', 100.0)
        ->assertSee('2 requests versus 1 previously: up 100%')
        ->assertSee('1 of 2 requests are cancelled (50%)')->assertDontSee('Private Person');
    $csv = $this->get(route('admin.dashboard', $response->viewData('filters') + ['export' => 'csv']))
        ->assertOk()->streamedContent();
    expect($csv)->toContain('Decision support', 'Suggested action', '2 requests versus 1 previously');
});

test('all time does not invent a previous period comparison', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.dashboard', ['all_time' => 1]))->assertOk()
        ->assertViewHas('decisionPreviousTotal', null)
        ->assertSee('A previous-period comparison is unavailable');
});

test('decision support remains restricted to administrators', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->get(route('admin.dashboard'))->assertForbidden();
});
