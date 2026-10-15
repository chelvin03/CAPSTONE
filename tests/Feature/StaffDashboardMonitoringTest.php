<?php

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-13 10:00:00', config('app.timezone')));
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->booking = fn (array $values = []) => Reservation::create(array_merge([
        'reference_number' => 'MONITOR-'.fake()->unique()->numerify('######'),
        'facility_id' => $this->gym->id, 'reservation_type' => 'internal',
        'event_name' => 'Student Basketball Practice', 'purpose' => 'Practice',
        'contact_person' => 'Private Coordinator', 'contact_number' => '09123456789',
        'reservation_date' => '2026-10-14', 'expected_attendees' => 50,
        'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved',
    ], $values));
});

test('staff year month and day filters consistently narrow event lists and status counts', function () {
    ($this->booking)(['status' => 'pencil']);
    ($this->booking)(['status' => 'pending']);
    ($this->booking)(['event_name' => 'October Practice']);
    ($this->booking)(['reservation_date' => '2026-11-14', 'event_name' => 'November Practice']);
    ($this->booking)(['reservation_date' => '2027-10-14', 'event_name' => 'Next Year Practice']);
    ($this->booking)(['reservation_date' => '2026-10-15', 'status' => 'cancelled']);
    $this->actingAs($this->staff);
    $this->get(route('staff.dashboard', ['year' => 2026, 'month' => 10, 'day' => 14]))->assertOk()
        ->assertViewHas('statusTotal', 3)
        ->assertViewHas('statusBreakdown', fn ($rows) => $rows->firstWhere('status', 'pencil')['count'] === 1 && $rows->firstWhere('status', 'pending')['count'] === 1)
        ->assertViewHas('upcomingReservations', fn ($rows) => $rows->total() === 1 && $rows->first()->event_name === 'October Practice')
        ->assertViewHas('upcomingCount', 1);
    $this->get(route('staff.dashboard', ['year' => 2026]))->assertViewHas('statusTotal', 5);
    $this->get(route('staff.dashboard', ['year' => 'all', 'month' => 10]))->assertViewHas('statusTotal', 5);
});

test('staff dashboard rejects invalid and incomplete date filters', function () {
    $this->actingAs($this->staff);
    foreach ([['year' => 'invalid'], ['month' => 13], ['day' => 14], ['year' => 2026, 'month' => 2, 'day' => 30]] as $filters) {
        $this->from(route('staff.dashboard'))->get(route('staff.dashboard', $filters))->assertSessionHasErrors();
    }
    $this->get(route('staff.dashboard', ['year' => 2028, 'month' => 2, 'day' => 29]))->assertOk();
});

test('today schedule hides preparation times while preserving records and live operational cards', function () {
    ($this->booking)(['reservation_date' => '2026-10-13', 'setup_time' => '08:30', 'cleanup_time' => '11:30']);
    ($this->booking)(['reservation_date' => '2026-10-13', 'start_time' => '07:00', 'end_time' => '08:00', 'status' => 'completed']);
    ($this->booking)();
    ($this->booking)(['reservation_date' => '2026-10-20']);
    ($this->booking)(['reservation_date' => '2026-10-21']);
    Equipment::create(['equipment_name' => 'Chairs', 'total_quantity' => 100, 'unit' => 'pcs', 'status' => 'available']);
    Equipment::create(['equipment_name' => 'Speaker', 'total_quantity' => 1, 'unit' => 'pcs', 'status' => 'maintenance']);
    $this->actingAs($this->staff)->get(route('staff.dashboard', ['year' => 2025]))->assertOk()
        ->assertViewHas('todayCount', 2)->assertViewHas('upcomingCount', 2)
        ->assertViewHas('statusTotal', 0)->assertViewHas('equipmentAttention', 1)
        ->assertViewHas('todayReservations', fn ($rows) => $rows->first()->status === 'completed')
        ->assertDontSee('Setup Time')->assertDontSee('Cleanup Time')->assertDontSee('08:30')->assertDontSee('11:30')->assertSee('View Details')
        ->assertDontSee('Prepare / Send AR')->assertDontSee('Manage AR')
        ->assertDontSee('Private Coordinator');
    $this->assertDatabaseHas('reservations', ['setup_time' => '08:30', 'cleanup_time' => '11:30']);
});

test('dashboard pagination retains filters and keeps event order', function () {
    foreach (range(1, 7) as $i) {
        ($this->booking)(['reservation_date' => '2026-10-'.(13 + $i)]);
    }
    $filters = ['year' => 2026, 'month' => 10];
    $this->actingAs($this->staff)->get(route('staff.dashboard', $filters))->assertOk()
        ->assertViewHas('upcomingReservations', function ($rows) {
            parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $params);
            return $rows->total() === 7 && $params['year'] === '2026' && $params['month'] === '10' && $params['upcoming_page'] === '2';
        });
    $this->get(route('staff.dashboard', $filters + ['upcoming_page' => 2]))->assertOk()
        ->assertViewHas('upcomingReservations', fn ($rows) => $rows->count() === 2 && $rows->first()->reservation_date->toDateString() === '2026-10-19');
});

test('staff dashboard renders an operational preview without modifying source data', function () {
    ($this->booking)(['reservation_date' => '2026-10-13', 'setup_time' => '08:30', 'cleanup_time' => '11:30']);
    ($this->booking)(['event_name' => 'Student Organization Assembly']);
    ($this->booking)(['status' => 'new']);
    Equipment::create(['equipment_name' => 'Chairs', 'total_quantity' => 100, 'unit' => 'pcs', 'status' => 'available']);
    $response = $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk();
    if (getenv('STAFF_DASHBOARD_PREVIEW')) {
        file_put_contents(base_path('.audit-inspection/staff-dashboard.html'), $response->getContent());
    }
    expect(Reservation::count())->toBe(3);
});

test('staff filtering does not change admin dashboard counts or administrator access', function () {
    ($this->booking)();
    ($this->booking)(['status' => 'cancelled']);
    $admin = User::factory()->create(['role' => 'admin']);
    $params = ['year' => 2026, 'month' => 10];
    $before = $this->actingAs($admin)->get(route('admin.dashboard', $params))->assertOk();
    $this->actingAs($this->staff)->get(route('staff.dashboard', ['year' => 2025]))->assertOk()->assertViewHas('statusTotal', 0);
    $this->get(route('admin.dashboard', $params))->assertForbidden();
    $after = $this->actingAs($admin)->get(route('admin.dashboard', $params))->assertOk();
    expect($after->viewData('totalReservations'))->toBe($before->viewData('totalReservations'))
        ->and($after->viewData('approvedReservations'))->toBe($before->viewData('approvedReservations'));
});
