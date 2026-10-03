<?php

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function staffMonitoringReservation(Facility $facility, array $attributes = []): Reservation
{
    return Reservation::create(array_merge([
        'reference_number' => 'STAFF-'.fake()->unique()->numerify('######'),
        'facility_id' => $facility->id, 'reservation_type' => 'student', 'event_name' => 'Operational Event',
        'event_type' => 'Sports', 'purpose' => 'PRIVATE PURPOSE', 'contact_person' => 'PRIVATE CONTACT',
        'contact_number' => '09991234567', 'contact_email' => 'private@example.test', 'admin_notes' => 'PRIVATE NOTES',
        'expected_attendees' => 20, 'reservation_date' => now()->toDateString(),
        'start_time' => '08:00', 'end_time' => '10:00', 'status' => 'approved',
    ], $attributes));
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-28 09:00:00', config('app.timezone')));
    $this->staff = User::factory()->create(['role' => 'staff', 'first_name' => 'Jamie', 'last_name' => 'Santos']);
    $this->gym = Facility::create(['facility_name' => 'MCST Main Gymnasium', 'capacity' => 200, 'status' => 'available']);
});

test('staff dashboard uses real counts sorted lists and next seven inclusive days', function () {
    foreach (['new', 'validated', 'approved', 'rejected', 'waiting_list', 'cancelled', 'completed'] as $status) {
        staffMonitoringReservation($this->gym, ['status' => $status, 'start_time' => $status === 'completed' ? '07:00' : '10:00']);
    }
    foreach ([1, 2, 3, 4, 5, 7, 8] as $days) {
        staffMonitoringReservation($this->gym, ['reservation_date' => now()->addDays($days)->toDateString()]);
    }
    staffMonitoringReservation($this->gym, ['reservation_date' => now()->addDays(7)->toDateString(), 'status' => 'cancelled']);
    staffMonitoringReservation($this->gym, ['reservation_date' => now()->addDay()->toDateString(), 'status' => 'rejected']);
    $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()->assertSee('Welcome, Jamie Santos.')
        ->assertViewHas('todayCount', 2)->assertViewHas('upcomingCount', 6)
        ->assertViewHas('todayReservations', fn ($rows) => $rows->count() === 2 && $rows->first()->status === 'completed')
        ->assertViewHas('upcomingReservations', fn ($rows) => $rows->count() === 5 && $rows->first()->reservation_date->toDateString() === '2026-09-29' && $rows->last()->reservation_date->toDateString() === '2026-10-03')
        ->assertDontSee(route('staff.reports.index'), false)->assertDontSee(route('admin.dashboard'), false)
        ->assertSee(route('staff.schedules.index'), false);
});

test('staff pages exclude private fields and display operational reservation details only', function () {
    $reservation = staffMonitoringReservation($this->gym);
    $this->actingAs($this->staff);
    foreach (['staff.dashboard', 'staff.reservations.index', 'staff.schedules.index'] as $route) {
        $this->get(route($route))->assertOk()->assertDontSee('PRIVATE CONTACT')->assertDontSee('09991234567')
            ->assertDontSee('private@example.test')->assertDontSee('PRIVATE NOTES')->assertDontSee('PRIVATE PURPOSE');
    }
    $this->get(route('staff.reservations.show', $reservation))->assertOk()->assertSee($reservation->reference_number)
        ->assertDontSee('PRIVATE CONTACT')->assertDontSee('09991234567')->assertDontSee('private@example.test')
        ->assertDontSee('PRIVATE NOTES')->assertDontSee('PRIVATE PURPOSE')
        ->assertViewHas('reservation', fn ($row) => ! array_key_exists('contact_email', $row->getAttributes()));
    $this->get(route('staff.reservations.index', ['search' => 'PRIVATE CONTACT']))->assertViewHas('reservations', fn ($rows) => $rows->total() === 0);
});

test('staff cannot enter admin routes or mutate operational records', function () {
    $reservation = staffMonitoringReservation($this->gym);
    $equipment = Equipment::create(['equipment_name' => 'Chairs', 'total_quantity' => 20, 'unit' => 'pcs', 'status' => 'available']);
    $this->actingAs($this->staff);
    foreach (['admin.dashboard', 'admin.reports.index', 'admin.reports.export', 'admin.reports.excel', 'admin.reports.word', 'admin.reservations.create', 'admin.facilities.create', 'admin.equipment.create'] as $route) {
        $this->get(route($route))->assertForbidden();
    }
    $this->get(route('admin.dashboard', ['export' => 'csv']))->assertForbidden();
    foreach (['approve', 'reject', 'cancel', 'reschedule'] as $action) {
        $this->patch(route('admin.reservations.'.$action, $reservation), [])->assertForbidden();
    }
    $this->post(route('admin.reservations.store'), [])->assertForbidden();
    $this->get(route('admin.reservations.edit', $reservation))->assertForbidden();
    $this->patch(route('admin.reservations.update', $reservation), ['status' => 'completed'])->assertForbidden();
    foreach (['facilities' => $this->gym, 'equipment' => $equipment] as $resource => $record) {
        $this->post(route('admin.'.$resource.'.store'), [])->assertForbidden();
        $this->get(route('admin.'.$resource.'.edit', $record))->assertForbidden();
        $this->put(route('admin.'.$resource.'.update', $record), [])->assertForbidden();
        $this->delete(route('admin.'.$resource.'.destroy', $record))->assertForbidden();
    }
    expect($reservation->fresh()->status)->toBe('approved');
    expect($this->gym->fresh())->not->toBeNull();
    expect($equipment->fresh())->not->toBeNull();
});

test('guest admin and unapproved staff follow staff access rules', function () {
    $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
    $this->get(route('staff.dashboard'))->assertForbidden();
    foreach (['pending', 'disabled', 'rejected'] as $status) {
        $this->staff->update(['status' => $status]);
        $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertForbidden();
    }
});

test('operational notices use maintenance capacity cancellations and schedule history without revealing notes', function () {
    $this->gym->update(['status' => 'maintenance']);
    Equipment::create(['equipment_name' => 'Chairs', 'total_quantity' => 100, 'unit' => 'pcs', 'status' => 'maintenance']);
    $event = staffMonitoringReservation($this->gym, ['expected_attendees' => 200]);
    $cancelled = staffMonitoringReservation($this->gym, ['status' => 'cancelled', 'cancelled_at' => now()->subDay(), 'cancellation_reason' => 'PRIVATE REASON']);
    DB::table('reservation_status_histories')->insert(['reservation_id' => $event->id, 'new_status' => 'approved', 'previous_status' => 'approved', 'remarks' => 'Reservation rescheduled: PRIVATE CHANGE', 'created_at' => now()->subDay()]);
    ScheduleBlock::create(['facility_id' => $this->gym->id, 'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(), 'title' => 'PRIVATE BLOCK', 'reason' => 'PRIVATE BLOCK REASON', 'created_by' => $this->staff->id]);
    $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()->assertSee('under maintenance')
        ->assertSee('meets or exceeds facility capacity')->assertSee('recently cancelled')->assertSee('schedule changed')
        ->assertSee('No equipment records are marked available')->assertSee('schedule block(s) affect today')
        ->assertDontSee('PRIVATE REASON')->assertDontSee('PRIVATE CHANGE')->assertDontSee('PRIVATE BLOCK');
    $this->get(route('staff.schedules.index'))->assertOk()->assertSee('Unavailable')->assertDontSee('PRIVATE BLOCK');
});

test('reservation filters and pagination preserve selected criteria', function () {
    foreach (range(1, 16) as $i) {
        staffMonitoringReservation($this->gym, ['event_name' => 'School Event '.$i]);
    }
    staffMonitoringReservation($this->gym, ['event_name' => 'Different Event']);
    staffMonitoringReservation($this->gym, ['event_name' => 'School Cancelled', 'status' => 'cancelled']);
    $filters = ['search' => 'School', 'date' => now()->toDateString(), 'status' => 'approved'];
    $this->actingAs($this->staff)->get(route('staff.reservations.index', $filters))->assertOk()
        ->assertViewHas('reservations', function ($rows) use ($filters) {
            parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $parameters);

            return $rows->total() === 16 && $rows->count() === 15 && array_diff_assoc($filters, $parameters) === [];
        });
    $this->get(route('staff.reservations.index', $filters + ['page' => 2]))->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->count() === 1);
    $this->from(route('staff.reservations.index'))->get(route('staff.reservations.index', ['status' => 'invalid']))->assertSessionHasErrors('status');
    $this->from(route('staff.schedules.index'))->get(route('staff.schedules.index', ['date' => '2026-02-30']))->assertSessionHasErrors('date');
});

test('schedule date filter only shows confirmed events and dashboard handles an empty database', function () {
    $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()->assertViewHas('todayCount', 0)
        ->assertViewHas('upcomingCount', 0)->assertSee('No gymnasium events are scheduled for today.')
        ->assertSee('No operational notices at this time.');
    $this->gym->delete();
    $this->get(route('staff.dashboard'))->assertOk()->assertSee('No facility records available.');
    $gym = Facility::create(['facility_name' => 'New Gym', 'capacity' => 200, 'status' => 'available']);
    foreach (['approved', 'completed', 'new', 'rejected', 'cancelled'] as $status) {
        staffMonitoringReservation($gym, ['status' => $status, 'reservation_date' => '2026-10-01']);
    }
    $this->get(route('staff.schedules.index', ['date' => '2026-10-01']))->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->total() === 2);
});

test('staff logout invalidates the authenticated session', function () {
    $this->actingAs($this->staff)->withSession(['monitoring_marker' => 'test'])->post(route('logout'))
        ->assertRedirect(route('login'))->assertSessionMissing('monitoring_marker');
    $this->assertGuest();
    $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
});
