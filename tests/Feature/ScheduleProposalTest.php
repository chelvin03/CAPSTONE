<?php

use App\Mail\{ReplacementScheduleProposed, ReservationScheduleChanged};
use App\Models\{Equipment, Facility, Reservation, ScheduleBlock, ScheduleProposal, User};
use Illuminate\Support\Facades\{Mail, URL};

beforeEach(function () {
    Mail::fake();
    $this->travelTo(now()->setDate(2026, 10, 18)->startOfDay());
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->booking = Reservation::create(['reference_number' => 'PROPOSE-'.uniqid(), 'facility_id' => $this->gym->id,
        'reservation_type' => 'internal', 'event_name' => 'Sports event', 'purpose' => 'Sports', 'contact_person' => 'Requestor',
        'contact_email' => 'requestor@example.com', 'contact_number' => '09123456789', 'expected_attendees' => 100,
        'reservation_date' => '2026-10-24', 'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved']);
    $this->data = ['reservation_date' => '2026-10-25', 'start_time' => '14:00', 'reason' => 'Priority event requires coordination.', 'send_proposal' => 1];
    $this->actingAs($this->admin);
    $this->propose = function (array $overrides = []) {
        $data = array_replace($this->data, $overrides);
        $this->patch(route('admin.reservations.reschedule', $this->booking), previewedReschedule($this->booking, $data))->assertSessionHasNoErrors();
        return ScheduleProposal::latest('id')->firstOrFail();
    };
    $this->link = fn ($proposal) => URL::temporarySignedRoute('reservation.schedule-proposal', $proposal->expires_at, ['proposal' => $proposal->id]);
});

test('proposal preview and email leave original booking unchanged and do not hold proposed time', function () {
    $preview = $this->postJson(route('admin.reservations.reschedule-preview', $this->booking), $this->data)->assertOk();
    expect($preview->json('summary'))->toContain('No bookings change now.');
    expect(ScheduleProposal::count())->toBe(0);
    Mail::assertNothingSent();
    $proposal = ($this->propose)();
    expect($this->booking->refresh()->reservation_date->toDateString())->toBe('2026-10-24')
        ->and(substr($this->booking->start_time, 0, 5))->toBe('09:00')
        ->and($proposal->target['end_time'])->toBe('16:00')
        ->and($proposal->refresh()->mail_status)->toBe('sent')
        ->and($proposal->expires_at->toDateTimeString())->toBe('2026-10-20 00:00:00');
    Mail::assertSent(ReplacementScheduleProposed::class, function ($mail) {
        expect($mail->render())->toContain('Original:', 'Proposed:', 'Priority event requires coordination.', 'proposed slot is not held');
        return $mail->hasTo('requestor@example.com');
    });
    Mail::assertNotSent(ReservationScheduleChanged::class);
    expect(Reservation::whereDate('reservation_date', '2026-10-25')->count())->toBe(0);
});

test('signed review link never accepts on get and acceptance works without login exactly once', function () {
    $proposal = ($this->propose)();
    $link = ($this->link)($proposal);
    auth()->logout();
    $this->get($link)->assertOk()->assertSee('Accept New Schedule')->assertHeader('Referrer-Policy', 'no-referrer');
    expect($proposal->refresh()->status)->toBe('pending');
    $this->post($link, ['decision' => 'accepted'])->assertRedirect($link)->assertSessionHasNoErrors();
    expect($proposal->refresh()->status)->toBe('accepted')->and($proposal->responded_at)->not->toBeNull()
        ->and($this->booking->refresh()->reservation_date->toDateString())->toBe('2026-10-25')
        ->and(substr($this->booking->end_time, 0, 5))->toBe('16:00')->and($this->booking->status)->toBe('approved');
    Mail::assertSent(ReservationScheduleChanged::class, fn ($mail) => str_contains($mail->render(), 'You accepted'));
    $this->post($link, ['decision' => 'accepted'])->assertSessionHasErrors('schedule');
    Mail::assertSentCount(2);
    $this->get($link)->assertDontSee('name="decision"', false)->assertSee('Accepted');
});

test('declining keeps the original schedule and prevents later acceptance', function () {
    $proposal = ($this->propose)();
    $link = ($this->link)($proposal);
    $this->post($link, ['decision' => 'declined'])->assertSessionHasNoErrors();
    expect($proposal->refresh()->status)->toBe('declined')->and($this->booking->refresh()->reservation_date->toDateString())->toBe('2026-10-24');
    $this->post($link, ['decision' => 'accepted'])->assertSessionHasErrors('schedule');
    Mail::assertNotSent(ReservationScheduleChanged::class);
    $this->get(route('admin.reservations.show', $this->booking))->assertOk()->assertSee('Declined');
});

test('expired unsigned and modified signed links cannot change a reservation', function () {
    $proposal = ($this->propose)();
    $link = ($this->link)($proposal);
    $this->post(route('reservation.schedule-proposal', $proposal), ['decision' => 'accepted'])->assertForbidden();
    $this->post($link.'&extra=changed', ['decision' => 'accepted'])->assertForbidden();
    $this->travel(49)->hours();
    $this->post($link, ['decision' => 'accepted'])->assertForbidden();
    expect($proposal->refresh()->status)->toBe('pending')->and($this->booking->refresh()->reservation_date->toDateString())->toBe('2026-10-24');
});

test('a new proposal supersedes the old private offer', function () {
    $old = ($this->propose)();
    $new = ($this->propose)(['start_time' => '17:00']);
    expect($old->refresh()->status)->toBe('superseded')->and($new->status)->toBe('pending');
    $this->post(($this->link)($old), ['decision' => 'accepted'])->assertSessionHasErrors('schedule');
    $this->post(($this->link)($new), ['decision' => 'accepted'])->assertSessionHasNoErrors();
    expect(substr($this->booking->refresh()->start_time, 0, 5))->toBe('17:00');
});

test('new confirmed bookings and cleaning gaps are rechecked without displacing anyone', function () {
    $proposal = ($this->propose)();
    $other = $this->booking->replicate();
    $other->fill(['reference_number' => 'OTHER-'.uniqid(), 'reservation_date' => '2026-10-25', 'start_time' => '16:00', 'end_time' => '18:00'])->save();
    $this->post(($this->link)($proposal), ['decision' => 'accepted', 'force_assign' => 1])->assertSessionHasErrors('schedule');
    expect($other->refresh()->status)->toBe('approved')->and($proposal->refresh()->status)->toBe('pending')
        ->and($this->booking->refresh()->reservation_date->toDateString())->toBe('2026-10-24');
});

test('administrator blocks added after sending an offer prevent acceptance', function () {
    $proposal = ($this->propose)();
    ScheduleBlock::create(['created_by' => $this->admin->id, 'title' => 'Repair', 'starts_on' => '2026-10-25', 'ends_on' => '2026-10-25']);
    $this->post(($this->link)($proposal), ['decision' => 'accepted'])->assertSessionHasErrors('schedule');
    expect($proposal->refresh()->status)->toBe('pending')->and($this->booking->refresh()->reservation_date->toDateString())->toBe('2026-10-24');
});

test('changed original schedule status or email invalidate the private offer', function ($change) {
    $proposal = ($this->propose)();
    $this->booking->update($change);
    $this->post(($this->link)($proposal), ['decision' => 'accepted'])->assertSessionHasErrors('schedule');
    expect($proposal->refresh()->status)->toBe('pending');
})->with([
    [['status' => 'cancelled']],
    [['start_time' => '08:00']],
    [['contact_email' => 'new-owner@example.com']],
]);

test('equipment availability is rechecked before accepting a proposal', function () {
    $equipment = Equipment::create(['equipment_name' => 'Speaker', 'total_quantity' => 5, 'status' => 'available']);
    $this->booking->equipment()->attach($equipment, ['quantity_requested' => 5, 'quantity_approved' => 5, 'status' => 'approved']);
    $proposal = ($this->propose)();
    $equipment->update(['status' => 'unavailable']);
    $this->post(($this->link)($proposal), ['decision' => 'accepted'])->assertSessionHasErrors('equipment');
    expect($this->booking->refresh()->reservation_date->toDateString())->toBe('2026-10-24');
});

test('proposals require email confirmed preview and cannot force assign', function () {
    $this->patch(route('admin.reservations.reschedule', $this->booking), $this->data)->assertSessionHasErrors('impact_token');
    $this->postJson(route('admin.reservations.reschedule-preview', $this->booking), [...$this->data, 'force_assign' => 1, 'conflict_resolution' => 'cancelled'])
        ->assertUnprocessable()->assertJsonValidationErrors('send_proposal');
    $this->booking->update(['contact_email' => null]);
    $this->postJson(route('admin.reservations.reschedule-preview', $this->booking), $this->data)->assertUnprocessable()->assertJsonValidationErrors('send_proposal');
    expect(ScheduleProposal::count())->toBe(0);
});

test('proposal cannot approve a pending reservation and preserves midnight duration', function () {
    $this->booking->update(['status' => 'new', 'start_time' => '21:00', 'end_time' => '24:00']);
    $proposal = ($this->propose)(['start_time' => '21:00']);
    expect($proposal->target['end_time'])->toBe('24:00');
    $this->post(($this->link)($proposal), ['decision' => 'accepted'])->assertSessionHasNoErrors();
    expect($this->booking->refresh()->status)->toBe('new')->and(substr($this->booking->end_time, 0, 5))->toBe('24:00');
});

test('staff cannot send proposals and invalid decisions cannot change bookings', function () {
    $proposal = ($this->propose)();
    $this->post(($this->link)($proposal), ['decision' => 'cancelled'])->assertSessionHasErrors('decision');
    $this->actingAs(User::factory()->create(['role' => 'staff']))->patch(route('admin.reservations.reschedule', $this->booking), $this->data)->assertForbidden();
    expect($proposal->refresh()->status)->toBe('pending');
});

test('email delivery failures are visible without changing the original booking', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Test mail transport failure'));
    $proposal = ($this->propose)();
    expect($proposal->refresh()->mail_status)->toBe('failed')
        ->and($this->booking->refresh()->reservation_date->toDateString())->toBe('2026-10-24');
    $this->get(route('admin.reservations.show', $this->booking))->assertOk()->assertSee('Email failed.');
});

test('reply deadline cannot extend past the proposed start and unchanged targets are rejected', function () {
    $proposal = ($this->propose)(['reservation_date' => '2026-10-19', 'start_time' => '09:00']);
    expect($proposal->expires_at->toDateTimeString())->toBe('2026-10-19 09:00:00');
    $data = [...$this->data, 'reservation_date' => '2026-10-24', 'start_time' => '09:00'];
    $this->patch(route('admin.reservations.reschedule', $this->booking), previewedReschedule($this->booking, $data))->assertSessionHasErrors('schedule');
    expect(ScheduleProposal::count())->toBe(1);
});

test('requestor can decline and open a new reservation without changing the original booking', function () {
    $proposal = ($this->propose)();
    $link = ($this->link)($proposal);
    auth()->logout();
    $this->get($link)->assertOk()->assertSee('Accept New Schedule')->assertSee('Decline New Schedule')->assertDontSee('Decline and Book Again')->assertDontSee('>Book Again</a>', false);
    $this->post($link, ['decision' => 'declined'])->assertRedirect($link)->assertSessionHasNoErrors();
    $this->get($link)->assertOk()->assertSee('Book Again')->assertSee(route('reservation.create'), false)->assertDontSee('name="decision"', false);
    expect($proposal->refresh()->status)->toBe('declined')
        ->and($this->booking->refresh()->status)->toBe('approved')
        ->and($this->booking->reservation_date->toDateString())->toBe('2026-10-24');
    $this->post($link, ['decision' => 'accepted'])->assertSessionHasErrors('schedule');
    Mail::assertNotSent(ReservationScheduleChanged::class);
});

test('ordinary rescheduling requires acceptance even when the client disables proposals', function () {
    $data = [...$this->data, 'send_proposal' => 0];
    $this->patch(route('admin.reservations.reschedule', $this->booking), previewedReschedule($this->booking, $data))->assertSessionHasNoErrors();
    expect($this->booking->refresh()->reservation_date->toDateString())->toBe('2026-10-24');
    $proposal = ScheduleProposal::latest('id')->firstOrFail();
    expect($proposal->status)->toBe('pending');
    Mail::assertSent(ReplacementScheduleProposed::class, function ($mail) {
        expect($mail->render())->toContain('Review New Schedule', 'Accept New Schedule', 'Decline New Schedule', '/schedule-proposals/');
        return true;
    });
    Mail::assertNotSent(ReservationScheduleChanged::class);
    $this->post(($this->link)($proposal), ['decision' => 'accepted'])->assertSessionHasNoErrors();
    expect($this->booking->refresh()->reservation_date->toDateString())->toBe('2026-10-25');
});

test('unchanged schedules cannot be previewed or saved and send no email', function () {
    foreach ([['send_proposal' => 0], ['send_proposal' => 1], ['send_proposal' => 0, 'force_assign' => 1, 'conflict_resolution' => 'cancelled']] as $mode) {
        $data = [...$this->data, ...$mode, 'reservation_date' => '2026-10-24', 'start_time' => '09:00'];
        $this->postJson(route('admin.reservations.reschedule-preview', $this->booking), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->patch(route('admin.reservations.reschedule', $this->booking), $data)->assertSessionHasErrors('schedule');
    }
    expect(ScheduleProposal::count())->toBe(0);
    Mail::assertNothingSent();
    expect($this->booking->refresh()->reservation_date->toDateString())->toBe('2026-10-24');
});

test('same day with different times can be proposed without changing approval status', function () {
    $this->booking->update(['status' => 'new']);
    $proposal = ($this->propose)(['reservation_date' => '2026-10-24']);
    $this->post(($this->link)($proposal), ['decision' => 'accepted'])->assertSessionHasNoErrors();
    expect($this->booking->refresh()->status)->toBe('new')
        ->and(substr($this->booking->start_time, 0, 5))->toBe('14:00');
});

test('proposal and force assignment reject durations under two hours', function () {
    foreach ([0, 1] as $force) {
        $data = [...$this->data, 'send_proposal' => 0, 'force_assign' => $force,
            'conflict_resolution' => 'cancelled', 'adjust_duration' => 1, 'end_time' => '15:30'];
        $this->postJson(route('admin.reservations.reschedule-preview', $this->booking), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->patch(route('admin.reservations.reschedule', $this->booking), $data)->assertSessionHasErrors('end_time');
    }
    expect(ScheduleProposal::count())->toBe(0);
    Mail::assertNothingSent();
});
