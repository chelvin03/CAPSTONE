<?php

use App\Models\AcknowledgementReceipt;
use App\Models\AcknowledgementTask;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AcknowledgementReceiptService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-17 10:00:00'));
    Mail::fake();
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->booking = fn ($attributes = []) => Reservation::create(array_merge([
        'reference_number' => 'AR-MANAGE-'.uniqid(), 'facility_id' => $this->gym->id, 'reservation_type' => 'internal',
        'event_name' => 'Gym Event', 'event_type' => 'Sports', 'purpose' => 'Activity', 'contact_person' => 'Event Coordinator',
        'contact_number' => '09123456789', 'contact_email' => 'coordinator@example.test', 'expected_attendees' => 50,
        'reservation_date' => '2026-10-22', 'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'new',
    ], $attributes));
});

test('approval creates one task atomically and backfill is idempotent', function () {
    $booking = ($this->booking)();
    expect(AcknowledgementTask::count())->toBe(0);
    $this->actingAs($this->admin)->patch(route('admin.reservations.approve', $booking))->assertSessionHasNoErrors();
    expect($booking->fresh()->acknowledgementTask->status)->toBe('pending');
    $booking->refresh()->save();
    expect(AcknowledgementTask::count())->toBe(1);
    AcknowledgementTask::query()->delete();
    $this->artisan('ar:backfill-tasks')->assertSuccessful();
    $this->artisan('ar:backfill-tasks')->assertSuccessful();
    expect(AcknowledgementTask::count())->toBe(1);
    foreach (['new', 'validated', 'rejected', 'waiting_list', 'cancelled', 'completed', 'pencil'] as $status) ($this->booking)(['status' => $status]);
    expect(AcknowledgementTask::count())->toBe(1);
    try {
        DB::transaction(function () { ($this->booking)(['status' => 'approved']); throw new RuntimeException('Rollback'); });
    } catch (RuntimeException) {}
    expect(AcknowledgementTask::count())->toBe(1);
});

test('staff starts previews generates downloads prints and completes an AR with real KPI updates', function () {
    $booking = ($this->booking)(['status' => 'approved']);
    $other = ($this->booking)(['status' => 'approved', 'reservation_date' => '2026-11-01']);
    $this->actingAs($this->staff)->get(route('staff.dashboard', ['year' => 2026, 'month' => 10]))->assertOk()
        ->assertViewHas('pendingArCount', 1)->assertViewHas('completedArCount', 0)->assertViewHas('approvedPeriodCount', 1)
        ->assertSeeInOrder(["Today's Events", 'Upcoming Reservations', 'Gym Availability', 'Equipment Status', 'Pending AR', 'Completed AR', 'Approved Events', 'Approved Reservations', "Today's Gymnasium Schedule", 'Reservation Status Overview'])
        ->assertDontSee('Recent Notifications');
    $this->post(route('staff.reservations.receipt.start', $booking))->assertRedirect(route('staff.reservations.receipt', $booking));
    expect($booking->fresh()->acknowledgementTask->status)->toBe('in_progress');
    $this->get(route('staff.reservations.receipt', $booking))->assertOk()->assertSee('Event Coordinator')->assertSee('Reservation Preview');
    $this->get(route('staff.reservations.receipt.pdf', $booking))->assertStatus(409);
    $this->post(route('staff.reservations.receipt.prepare', $booking), [])->assertSessionHasNoErrors();
    $receipt = AcknowledgementReceipt::sole();
    $pdf = $this->get(route('staff.reservations.receipt.pdf', $booking))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
    expect($pdf->getContent())->toStartWith('%PDF-');
    $this->get(route('staff.reservations.receipt.print', $booking))->assertOk()->assertSee('Date Issued')->assertSee('Sports');
    $this->post(route('staff.reservations.receipt.complete', $booking), ['version' => $receipt->version])->assertSessionHasNoErrors();
    $this->post(route('staff.reservations.receipt.complete', $booking), ['version' => $receipt->version])->assertSessionHasNoErrors();
    $this->get(route('staff.dashboard', ['year' => 2026, 'month' => 10]))->assertOk()->assertViewHas('pendingArCount', 0)->assertViewHas('completedArCount', 1);
    expect(AcknowledgementTask::count())->toBe(2)->and($booking->fresh()->status)->toBe('approved')
        ->and($booking->fresh()->acknowledgementTask->completed_by)->toBe($this->staff->id);
    Mail::assertNothingSent();
});

test('unique ARs and cancellation history are preserved while inactive actions are blocked', function () {
    $first = ($this->booking)(['status' => 'approved']);
    $second = ($this->booking)(['status' => 'approved']);
    $this->actingAs($this->staff);
    foreach ([$first, $second] as $booking) {
        $this->post(route('staff.reservations.receipt.prepare', $booking), [])->assertSessionHasNoErrors();
        $this->post(route('staff.reservations.receipt.prepare', $booking), [])->assertSessionHasNoErrors();
    }
    expect(AcknowledgementReceipt::count())->toBe(2)->and(AcknowledgementReceipt::distinct()->count('receipt_number'))->toBe(2);
    $first->update(['status' => 'cancelled']);
    $this->get(route('staff.ar.index'))->assertOk()->assertDontSee($first->reference_number)->assertSee($second->reference_number);
    $this->get(route('staff.ar.index', ['history' => 1]))->assertOk()->assertSee($first->reference_number)->assertSee('Inactive booking; history retained');
    foreach (['start', 'complete', 'prepare'] as $action) $this->post(route('staff.reservations.receipt.'.$action, $first), [])->assertForbidden();
    $this->get(route('staff.reservations.receipt.pdf', $first))->assertForbidden();
    expect(AcknowledgementReceipt::count())->toBe(2)->and(AcknowledgementTask::count())->toBe(2);
});

test('changed reservations invalidate completion and stale versions cannot complete receipts', function () {
    $booking = ($this->booking)(['status' => 'approved']);
    $this->actingAs($this->staff)->post(route('staff.reservations.receipt.prepare', $booking), [])->assertSessionHasNoErrors();
    $version = AcknowledgementReceipt::sole()->version;
    $this->post(route('staff.reservations.receipt.complete', $booking), ['version' => $version])->assertSessionHasNoErrors();
    $booking->update(['start_time' => '10:00']);
    expect($booking->fresh()->acknowledgementTask->status)->toBe('pending');
    $this->post(route('staff.reservations.receipt.complete', $booking), ['version' => $version])->assertSessionHasErrors('receipt');
    $this->get(route('staff.reservations.receipt.pdf', $booking))->assertStatus(409);
    $this->post(route('staff.reservations.receipt.prepare', $booking), [])->assertSessionHasNoErrors();
    $this->post(route('staff.reservations.receipt.complete', $booking), ['version' => $version])->assertSessionHasErrors('receipt');
});

test('AR actions enforce approved staff access and preserve all reservation restrictions', function () {
    $booking = ($this->booking)(['status' => 'approved']);
    $this->get(route('staff.ar.index'))->assertRedirect(route('login'));
    $this->actingAs($this->admin)->get(route('staff.ar.index'))->assertForbidden();
    $this->staff->update(['status' => 'disabled']);
    $this->actingAs($this->staff)->get(route('staff.ar.index'))->assertForbidden();
    $this->post(route('staff.reservations.receipt.start', $booking))->assertForbidden();
    $this->staff->update(['status' => 'approved']);
    foreach (['approve', 'reject', 'cancel', 'reschedule'] as $action) $this->patch(route('admin.reservations.'.$action, $booking), [])->assertForbidden();
    $this->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('staff.ar.index', ['status' => 'forged']))->assertSessionHasErrors('status');
});

test('gym availability reflects approved schedules and block overrides', function () {
    ($this->booking)(['status' => 'approved', 'reservation_date' => '2026-10-17']);
    $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()->assertViewHas('gymAvailability', 'Occupied');
    \App\Models\ScheduleBlock::create(['facility_id' => $this->gym->id, 'starts_on' => '2026-10-17', 'ends_on' => '2026-10-17', 'title' => 'Closed', 'created_by' => $this->admin->id]);
    $this->get(route('staff.dashboard'))->assertOk()->assertViewHas('gymAvailability', 'Unavailable');
});

test('backfill preserves existing receipt content versions and legacy snapshots', function () {
    $booking = ($this->booking)(['status' => 'approved']);
    $this->actingAs($this->staff)->post(route('staff.reservations.receipt.prepare', $booking), [])->assertSessionHasNoErrors();
    $receipt = AcknowledgementReceipt::sole();
    $snapshot = $receipt->snapshot;
    unset($snapshot['booking']['event_type']);
    $receipt->update(['snapshot' => $snapshot]);
    AcknowledgementTask::query()->delete();
    $this->artisan('ar:backfill-tasks')->assertSuccessful();
    expect($booking->fresh()->acknowledgementTask->status)->toBe('in_progress')
        ->and($receipt->fresh()->snapshot)->toBe($snapshot)->and($receipt->fresh()->version)->toBe($receipt->version);
    $this->get(route('staff.reservations.receipt.print', $booking))->assertOk();
});

test('equipment allocation changes reopen completed tasks and block outdated documents', function () {
    $booking = ($this->booking)(['status' => 'approved']);
    $equipment = Equipment::create(['equipment_name' => 'Chairs', 'total_quantity' => 100, 'unit' => 'pieces', 'status' => 'available']);
    $booking->equipment()->attach($equipment, ['quantity_requested' => 20, 'quantity_approved' => 5, 'status' => 'approved']);
    $this->actingAs($this->staff)->post(route('staff.reservations.receipt.prepare', $booking), [])->assertSessionHasNoErrors();
    $this->post(route('staff.reservations.receipt.complete', $booking), ['version' => AcknowledgementReceipt::sole()->version])->assertSessionHasNoErrors();
    app(\App\Services\EquipmentRequestService::class)->approveQuantity($booking, $equipment, 10);
    expect($booking->fresh()->acknowledgementTask->status)->toBe('pending');
    $this->get(route('staff.reservations.receipt.pdf', $booking))->assertStatus(409);
});

test('AR queue prioritizes nearest upcoming pending dates and retains recent past tasks', function () {
    $past = ($this->booking)(['status' => 'approved', 'reservation_date' => '2026-10-16']);
    $later = ($this->booking)(['status' => 'approved', 'reservation_date' => '2026-10-25']);
    $nearest = ($this->booking)(['status' => 'approved', 'reservation_date' => '2026-10-18']);
    $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()
        ->assertViewHas('arReservations', fn ($rows) => $rows->pluck('id')->all() === [$nearest->id, $later->id, $past->id]);
    $this->get(route('staff.ar.index'))->assertOk()
        ->assertViewHas('reservations', fn ($rows) => $rows->pluck('id')->all() === [$nearest->id, $later->id, $past->id]);
});
