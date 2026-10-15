<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', config('app.timezone')));
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    foreach (['2026-10-12', '2026-10-13', '2024-02-29'] as $date) {
        Reservation::create(['reference_number' => 'DAY-'.$date, 'user_id' => $admin->id,
            'facility_id' => $gym->id, 'reservation_type' => 'internal', 'event_name' => 'Practice', 'purpose' => 'Practice',
            'contact_person' => 'Coordinator', 'contact_number' => '09123456789', 'expected_attendees' => 50,
            'reservation_date' => $date, 'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved']);
    }
    $this->actingAs($admin);
});

test('day selector scopes summary to one date and omitting day scopes whole month', function () {
    $this->get(route('admin.dashboard', ['year' => 2026, 'month' => 10, 'day' => 12]))->assertOk()
        ->assertViewHas('totalReservations', 1)
        ->assertViewHas('filters', fn ($filters) => $filters['date_from'] === '2026-10-12' && $filters['date_to'] === '2026-10-12')
        ->assertSee('name="day"', false)->assertDontSee('Today')->assertDontSee('This Month')->assertDontSee('This Year');
    $this->get(route('admin.dashboard', ['year' => 2026, 'month' => 10]))->assertOk()->assertViewHas('totalReservations', 2);
});

test('day selector handles leap February and rejects invalid calendar dates', function () {
    $this->get(route('admin.dashboard', ['year' => 2024, 'month' => 2, 'day' => 29]))->assertOk()->assertViewHas('totalReservations', 1);
    foreach ([['year' => 2026, 'month' => 2, 'day' => 29], ['year' => 2026, 'month' => 4, 'day' => 31], ['year' => 'all', 'month' => 10, 'day' => 12], ['year' => 2026, 'day' => 12]] as $filters) {
        $this->get(route('admin.dashboard', $filters))->assertRedirect(route('admin.dashboard'))->assertSessionHasErrors('day');
    }
});
