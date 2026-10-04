<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\Carbon;

function adminScheduleBooking(Facility $facility, array $overrides = []): Reservation
{
    return Reservation::create(array_merge([
        'reference_number' => 'SCHEDULE-'.fake()->unique()->numerify('######'),
        'facility_id' => $facility->id, 'reservation_type' => 'student',
        'event_name' => 'Test school event', 'event_type' => 'Sports',
        'purpose' => 'Private purpose', 'contact_person' => 'Private contact',
        'contact_email' => 'private-schedule@example.test', 'contact_number' => '09123456789',
        'expected_attendees' => 30, 'reservation_date' => '2026-10-06',
        'start_time' => '09:15:00', 'end_time' => '10:45:00', 'status' => 'approved',
    ], $overrides));
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00:00', config('app.timezone')));
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '17:00']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->facility = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 200, 'status' => 'available']);
    $this->actingAs($this->admin);
});

test('admin schedule displays exact approved and available intervals without contacts', function () {
    $booking = adminScheduleBooking($this->facility, ['setup_time' => '08:30', 'cleanup_time' => '11:00']);
    $response = $this->get(route('admin.schedule.index', ['date' => '2026-10-06']));
    $response->assertOk()->assertSee('Gymnasium Schedule')->assertSee($booking->reference_number)
        ->assertSee(route('admin.reservations.show', $booking), false)->assertDontSee('Private contact')
        ->assertDontSee('private-schedule@example.test')->assertDontSee('Private purpose');
    $day = $response->viewData('days')['2026-10-06'];
    expect($day['state'])->toBe('partial')->and($day['approvedCount'])->toBe(1);
    $intervals = collect($day['facilities'][0]['intervals']);
    expect($intervals->where('state', 'available')->sum('seconds'))->toBe(27000)
        ->and($intervals->where('state', 'occupied')->sole()['time'])->toBe('9:15 AM – 10:45 AM');
});

test('overlapping pending requests are not confirmed and are split at exact boundaries', function () {
    adminScheduleBooking($this->facility, ['status' => 'new', 'start_time' => '09:00', 'end_time' => '11:00']);
    adminScheduleBooking($this->facility, ['status' => 'validated', 'start_time' => '10:30', 'end_time' => '12:00']);
    $day = $this->get(route('admin.schedule.index'))->assertOk()->viewData('days')['2026-10-06'];
    $intervals = collect($day['facilities'][0]['intervals']);
    expect($day['approvedCount'])->toBe(0)->and($day['pendingCount'])->toBe(2)
        ->and($day['conflictCount'])->toBe(1)->and($intervals->where('state', 'occupied'))->toHaveCount(0);
    $overlap = $intervals->where('conflict', true)->sole();
    expect($overlap['time'])->toBe('10:30 AM – 11:00 AM')->and($overlap['state'])->toBe('pending')
        ->and($overlap['pendingCount'])->toBe(2);
});

test('approved and pending overlap stays occupied while identifying pending conflicts', function () {
    adminScheduleBooking($this->facility);
    adminScheduleBooking($this->facility, ['status' => 'waiting_list']);
    $day = $this->get(route('admin.schedule.index'))->viewData('days')['2026-10-06'];
    $overlap = collect($day['facilities'][0]['intervals'])->where('conflict', true)->sole();
    expect($overlap['state'])->toBe('occupied')->and($overlap['approvedCount'])->toBe(1)->and($overlap['pendingCount'])->toBe(1);
});

test('status and event filters cannot erase approved occupancy', function () {
    $approved = adminScheduleBooking($this->facility);
    $pending = adminScheduleBooking($this->facility, ['status' => 'new', 'event_type' => 'Community', 'start_time' => '12:00', 'end_time' => '13:00']);
    $response = $this->get(route('admin.schedule.index', ['status' => 'new', 'event_type' => 'Community']));
    $response->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->pluck('id')->all() === [$pending->id]);
    expect($response->viewData('days')['2026-10-06']['approvedCount'])->toBe(1);
});

test('cancelled and rejected records remain visible but do not consume availability', function () {
    adminScheduleBooking($this->facility, ['status' => 'cancelled']);
    adminScheduleBooking($this->facility, ['status' => 'rejected']);
    $response = $this->get(route('admin.schedule.index'))->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->count() === 2);
    $day = $response->viewData('days')['2026-10-06'];
    expect($day['state'])->toBe('available')->and($day['approvedCount'])->toBe(0)->and($day['pendingCount'])->toBe(0);
});

test('blocked dates and partial blocks never appear fully available', function () {
    ScheduleBlock::create(['starts_on' => '2026-10-06', 'ends_on' => '2026-10-06', 'title' => 'Partial closure', 'start_time' => '12:15', 'end_time' => '13:40', 'created_by' => $this->admin->id]);
    ScheduleBlock::create(['starts_on' => '2026-10-07', 'ends_on' => '2026-10-07', 'title' => 'Closed', 'created_by' => $this->admin->id]);
    adminScheduleBooking($this->facility, ['reservation_date' => '2026-10-07']);
    $days = $this->get(route('admin.schedule.index'))->assertOk()->viewData('days');
    expect($days['2026-10-06']['state'])->toBe('partial')->and($days['2026-10-07']['state'])->toBe('closed');
    $closed = collect($days['2026-10-06']['facilities'][0]['intervals'])->firstWhere('time', '12:15 PM – 1:40 PM');
    expect($closed['state'])->toBe('closed')->and($closed['reasons'])->toContain('Administrator schedule block')
        ->and($days['2026-10-07']['conflictCount'])->toBeGreaterThan(0);
});

test('missing hours cannot yield availability and historical intervals are closed', function () {
    config(['gym.reservation.opening_time' => null]);
    $response = $this->get(route('admin.schedule.index'))->assertOk()->assertSee('Availability cannot be confirmed');
    $days = $response->viewData('days');
    expect($days['2026-10-06']['state'])->toBe('unknown')->and($days['2026-10-06']['hasAvailable'])->toBeFalse()
        ->and($days['2026-10-04']['state'])->toBe('closed');
});

test('configured hours and current-day past times are respected', function () {
    config(['gym.reservation.opening_time' => '07:45', 'gym.reservation.closing_time' => '16:20']);
    $response = $this->get(route('admin.schedule.index'))->assertOk();
    $day = $response->viewData('days')['2026-10-05'];
    $intervals = collect($day['facilities'][0]['intervals']);
    expect($intervals->where('state', 'available')->sole()['time'])->toBe('10:00 AM – 4:20 PM');
    expect($response->viewData('days')['2026-10-04']['hasAvailable'])->toBeFalse();
});

test('facilities have independent occupancy and the filter only appears for multiple reservable facilities', function () {
    $this->get(route('admin.schedule.index'))->assertOk()->assertDontSee('id="schedule-facility"', false);
    $second = Facility::create(['facility_name' => 'Second Gymnasium', 'capacity' => 100, 'status' => 'available']);
    adminScheduleBooking($this->facility);
    $response = $this->get(route('admin.schedule.index'))->assertOk()->assertSee('id="schedule-facility"', false);
    $panels = collect($response->viewData('days')['2026-10-06']['facilities']);
    expect(collect($panels->firstWhere('id', $second->id)['intervals'])->where('state', 'occupied'))->toHaveCount(0);
    $filtered = $this->get(route('admin.schedule.index', ['facility_id' => $second->id]))->assertOk();
    expect($filtered->viewData('days')['2026-10-06']['state'])->toBe('available')
        ->and($filtered->viewData('days')['2026-10-06']['facilities'])->toHaveCount(1);
});

test('month and date navigation handles leap years and invalid filters', function () {
    $response = $this->get(route('admin.schedule.index', ['month' => '2028-02', 'date' => '2028-02-29']))->assertOk();
    expect($response->viewData('days'))->toHaveCount(29)->and($response->viewData('selectedDate'))->toBe('2028-02-29')
        ->and($response->viewData('previousMonth'))->toBe('2028-01')->and($response->viewData('nextMonth'))->toBe('2028-03');
    $this->getJson(route('admin.schedule.index', ['month' => '2026-13', 'status' => 'invented', 'facility_id' => 999]))
        ->assertUnprocessable()->assertJsonValidationErrors(['month', 'status', 'facility_id']);
    $this->getJson(route('admin.schedule.index', ['date' => '2026-02-30']))->assertUnprocessable()->assertJsonValidationErrors('date');
});

test('admin schedule is protected from guests and staff', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->get(route('admin.schedule.index'))->assertForbidden();
    $this->post(route('admin.schedule.blocks.store'), [])->assertForbidden();
    auth()->logout();
    $this->get(route('admin.schedule.index'))->assertRedirect(route('login'));
});

test('empty data and unavailable facilities do not invent bookings or availability', function () {
    $this->facility->update(['status' => 'maintenance']);
    $response = $this->get(route('admin.schedule.index'))->assertOk()->assertSee('No reservations match');
    expect($response->viewData('days')['2026-10-06']['state'])->toBe('closed');
    $this->facility->delete();
    $response = $this->get(route('admin.schedule.index'))->assertOk()->assertSee('No facilities configured');
    expect($response->viewData('days')['2026-10-06']['hasAvailable'])->toBeFalse();
});

test('existing schedule block workflow requires paired times and allows releasing a block', function () {
    $data = ['title' => 'Maintenance', 'starts_on' => '2026-10-06', 'ends_on' => '2026-10-06'];
    $this->from(route('admin.schedule.index'))->post(route('admin.schedule.blocks.store'), [...$data, 'start_time' => '09:00'])
        ->assertSessionHasErrors('end_time');
    $this->post(route('admin.schedule.blocks.store'), [...$data, 'start_time' => '09:00', 'end_time' => '10:15'])->assertSessionHasNoErrors();
    $block = ScheduleBlock::sole();
    $this->delete(route('admin.schedule.blocks.destroy', $block))->assertRedirect();
    $this->assertDatabaseMissing('schedule_blocks', ['id' => $block->id]);
});

test('adjacent bookings do not conflict and full-day approved and pending states remain distinct', function () {
    adminScheduleBooking($this->facility, ['start_time' => '08:00', 'end_time' => '12:30']);
    adminScheduleBooking($this->facility, ['start_time' => '12:30', 'end_time' => '17:00']);
    adminScheduleBooking($this->facility, ['reservation_date' => '2026-10-07', 'status' => 'new', 'start_time' => '08:00', 'end_time' => '17:00']);
    $days = $this->get(route('admin.schedule.index'))->assertOk()->viewData('days');
    expect($days['2026-10-06']['state'])->toBe('occupied')->and($days['2026-10-06']['conflictCount'])->toBe(0)
        ->and($days['2026-10-06']['hasAvailable'])->toBeFalse()->and($days['2026-10-07']['state'])->toBe('pending')
        ->and($days['2026-10-07']['approvedCount'])->toBe(0);
});

test('a date with approved and pending intervals is mixed even when no free interval remains', function () {
    adminScheduleBooking($this->facility, ['start_time' => '08:00', 'end_time' => '12:00']);
    adminScheduleBooking($this->facility, ['status' => 'new', 'start_time' => '12:00', 'end_time' => '17:00']);
    $day = $this->get(route('admin.schedule.index'))->assertOk()->viewData('days')['2026-10-06'];
    expect($day['state'])->toBe('partial')->and($day['hasAvailable'])->toBeFalse()
        ->and($day['approvedCount'])->toBe(1)->and($day['pendingCount'])->toBe(1);
});
