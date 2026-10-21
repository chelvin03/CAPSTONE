<?php

use App\Mail\ReservationCancelled;
use App\Mail\ReservationScheduleChanged;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->date = now()->addDays(5)->toDateString();
    $this->make = function (string $email, string $start, string $end) {
        return Reservation::create(['reference_number' => 'FORCE-'.strtoupper(uniqid()), 'facility_id' => $this->gym->id,
            'reservation_type' => 'internal', 'event_name' => 'School Event', 'purpose' => 'Sports', 'contact_person' => 'Requestor',
            'contact_email' => $email, 'contact_number' => '09123456789', 'expected_attendees' => 100,
            'reservation_date' => $this->date, 'start_time' => $start, 'end_time' => $end, 'status' => 'approved']);
    };
    $this->moving = ($this->make)('moving@example.com', '14:00', '16:00');
    $this->occupied = ($this->make)('occupied@example.com', '09:00', '11:00');
    $this->data = ['reservation_date' => $this->date, 'start_time' => '09:00', 'end_time' => '11:00',
        'force_assign' => 1, 'conflict_resolution' => 'cancelled', 'reason' => 'Urgent school activity.'];
    $this->actingAs($this->admin);
    $this->reschedule = function (array $data) {
        return $this->patch(route('admin.reservations.reschedule', $this->moving), previewedReschedule($this->moving, $data));
    };
});

test('force cancellation emails the displaced and rescheduled requestors with clear outcomes', function () {
    ($this->reschedule)($this->data)->assertSessionHasNoErrors();
    expect($this->occupied->refresh()->status)->toBe('cancelled')
        ->and($this->occupied->cancellation_reason)->toBe('Urgent school activity.')
        ->and(substr($this->moving->refresh()->start_time, 0, 5))->toBe('09:00');
    Mail::assertSent(ReservationCancelled::class, fn ($mail) => $mail->hasTo('occupied@example.com'));
    Mail::assertSent(ReservationScheduleChanged::class, function ($mail) {
        expect($mail->render())->toContain('Previous schedule:', '14:00', 'New schedule:', '09:00', 'Urgent school activity.');
        return $mail->hasTo('moving@example.com') && $mail->outcome === 'rescheduled';
    });
    Mail::assertSentCount(2);
    $response = $this->getJson(route('reservation.track', ['reference' => $this->occupied->reference_number]))->assertJsonPath('status', 'cancelled');
    expect($response->json('html'))->toContain('Urgent school activity.');
});

test('force waiting list emails affected requestors without calling it cancellation', function () {
    ($this->reschedule)([...$this->data, 'conflict_resolution' => 'waiting_list'])->assertSessionHasNoErrors();
    expect($this->occupied->refresh()->status)->toBe('waiting_list')->and($this->occupied->cancelled_at)->toBeNull();
    Mail::assertNotSent(ReservationCancelled::class);
    Mail::assertSent(ReservationScheduleChanged::class, function ($mail) {
        if ($mail->outcome !== 'waiting_list') return false;
        expect($mail->render())->toContain('no longer confirmed', 'Urgent school activity.');
        return $mail->hasTo('occupied@example.com');
    });
    Mail::assertSentCount(2);
});

test('moving into a free slot proposes the change only to the moving requestor', function () {
    ($this->reschedule)([...$this->data, 'start_time' => '12:00', 'end_time' => '13:00', 'force_assign' => 0])->assertSessionHasNoErrors();
    expect($this->occupied->refresh()->status)->toBe('approved');
    Mail::assertSent(\App\Mail\ReplacementScheduleProposed::class, fn ($mail) => $mail->hasTo('moving@example.com'));
    Mail::assertNotSent(ReservationScheduleChanged::class);
    Mail::assertSentCount(1);
});

test('ordinary overlap and invalid resolution send no mail and do not change schedules', function () {
    ($this->reschedule)([...$this->data, 'force_assign' => 0])->assertSessionHasErrors('reservation_date');
    ($this->reschedule)([...$this->data, 'conflict_resolution' => 'invalid'])->assertSessionHasErrors('conflict_resolution');
    expect($this->occupied->refresh()->status)->toBe('approved')->and(substr($this->moving->refresh()->start_time, 0, 5))->toBe('14:00');
    Mail::assertNothingSent();
});

test('force assignment cannot override a schedule block', function () {
    ScheduleBlock::create(['facility_id' => $this->gym->id, 'created_by' => $this->admin->id,
        'starts_on' => $this->date, 'ends_on' => $this->date, 'title' => 'Closure']);
    ($this->reschedule)($this->data)->assertSessionHasErrors('reservation_date');
    expect($this->occupied->refresh()->status)->toBe('approved');
    Mail::assertNothingSent();
});

test('direct admin cancellation also emails the requestor', function () {
    $this->patch(route('admin.reservations.cancel', $this->moving), ['cancellation_reason' => 'Emergency closure.'])->assertSessionHasNoErrors();
    Mail::assertSent(ReservationCancelled::class, fn ($mail) => $mail->hasTo('moving@example.com') && $mail->reservation->cancellation_reason === 'Emergency closure.');
});

test('staff cannot force assign schedules', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->patch(route('admin.reservations.reschedule', $this->moving), $this->data)->assertForbidden();
    Mail::assertNothingSent();
});

test('midnight end time is visible and can be retained when rescheduling', function () {
    $this->moving->update(['start_time' => '17:00', 'end_time' => '24:00']);
    $this->get(route('admin.reservations.show', $this->moving))->assertOk()
        ->assertSee('Start Time')->assertSee('End Time')->assertSee('12:00 midnight (end of selected date)')
        ->assertSee('value="24:00" selected', false);
    ($this->reschedule)([...$this->data,
        'reservation_date' => now()->addDays(6)->toDateString(), 'start_time' => '17:00', 'end_time' => '24:00', 'force_assign' => 0,
    ])->assertSessionHasNoErrors();
    expect(substr($this->moving->refresh()->end_time, 0, 5))->toBe('24:00');
    Mail::assertSent(\App\Mail\ReplacementScheduleProposed::class, fn ($mail) => $mail->hasTo('moving@example.com'));
    Mail::assertNotSent(ReservationScheduleChanged::class);
});

test('a failed equipment check rolls back displacement and sends no email', function () {
    $this->partialMock(\App\Services\EquipmentRequestService::class, function ($mock) {
        $mock->shouldReceive('assertAllocations')->once()->andThrow(
            \Illuminate\Validation\ValidationException::withMessages(['equipment' => 'Inventory changed.'])
        );
    });
    ($this->reschedule)($this->data)->assertSessionHasErrors('equipment');
    expect($this->occupied->refresh()->status)->toBe('approved')
        ->and(substr($this->moving->refresh()->start_time, 0, 5))->toBe('14:00');
    Mail::assertNothingSent();
    $this->assertDatabaseMissing('reservation_status_histories', ['reservation_id' => $this->occupied->id, 'new_status' => 'cancelled']);
});

test('rescheduling preserves seven hours and ignores a silently shortened submitted end', function () {
    $this->moving->update(['start_time' => '17:00', 'end_time' => '24:00']);
    ($this->reschedule)([...$this->data,
        'reservation_date' => now()->addDays(6)->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00', 'force_assign' => 0,
    ])->assertSessionHasNoErrors();
    expect(substr($this->moving->refresh()->end_time, 0, 5))->toBe('24:00');
    Mail::assertSent(\App\Mail\ReplacementScheduleProposed::class, fn ($mail) => $mail->proposal->target['end_time'] === '17:00');
});

test('duration preservation refuses overflow instead of shortening or wrapping to the next day', function () {
    $this->moving->update(['start_time' => '17:00', 'end_time' => '24:00']);
    ($this->reschedule)([...$this->data,
        'start_time' => '18:00', 'end_time' => '24:00', 'force_assign' => 0,
    ])->assertSessionHasErrors('end_time');
    expect(substr($this->moving->refresh()->start_time, 0, 5))->toBe('17:00');
    Mail::assertNothingSent();
});

test('admin can deliberately shorten the booking with a recorded reason', function () {
    $this->moving->update(['start_time' => '17:00', 'end_time' => '24:00']);
    ($this->reschedule)([...$this->data,
        'reservation_date' => now()->addDays(6)->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00',
        'adjust_duration' => 1, 'force_assign' => 0, 'reason' => 'Requestor agreed to a two-hour event.',
    ])->assertSessionHasNoErrors();
    expect(substr($this->moving->refresh()->end_time, 0, 5))->toBe('24:00');
    $this->assertDatabaseHas('schedule_proposals', ['reservation_id' => $this->moving->id,
        'status' => 'pending', 'reason' => 'Requestor agreed to a two-hour event.']);
    Mail::assertSent(\App\Mail\ReplacementScheduleProposed::class, fn ($mail) => $mail->proposal->target['end_time'] === '12:00');
});

test('the entire preserved duration and cleaning gap are checked for conflicts', function () {
    $this->moving->update(['start_time' => '17:00', 'end_time' => '24:00']);
    ($this->make)('later@example.com', '18:00', '20:00');
    ($this->reschedule)([...$this->data,
        'start_time' => '11:30', 'end_time' => '12:30', 'force_assign' => 0,
    ])->assertSessionHasErrors('reservation_date');
    Mail::assertNothingSent();
});
