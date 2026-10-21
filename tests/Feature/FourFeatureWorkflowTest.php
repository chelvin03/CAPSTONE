<?php

use App\Mail\{ReservationApproved, ReservationCancelled};
use App\Models\{Facility, Reservation, ScheduleBlock, User};
use App\Services\ReservationReportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 21)->setTime(9, 0));
    Mail::fake();
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->date = now()->addDays(8)->toDateString();
    $this->makeBooking = function (array $overrides = []) {
        return Reservation::create(array_merge(['reference_number' => 'FLOW-'.uniqid(), 'facility_id' => $this->gym->id,
            'reservation_type' => 'internal', 'event_type' => config('event-types')[0], 'event_name' => 'Sports Activities',
            'purpose' => 'Sports', 'contact_person' => 'Requestor', 'contact_email' => 'requestor@example.com',
            'contact_number' => '09123456789', 'expected_attendees' => 100, 'reservation_date' => $this->date,
            'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved'], $overrides));
    };
    $this->payload = ['reservation_type' => 'internal', 'event_type' => config('event-types')[0], 'purpose' => 'Sports',
        'contact_person' => 'Waiting Requestor', 'contact_email' => 'waiting@example.com', 'contact_number' => '09123456789',
        'expected_attendees' => 100, 'reservation_date' => $this->date, 'start_time' => '09:00', 'end_time' => '11:00',
        'agreement' => 1, 'agreement_accepted' => 1,
        'permit' => UploadedFile::fake()->create('approval.pdf', 10, 'application/pdf')];
    $this->proof = ['public_email_verified' => ['email' => 'waiting@example.com', 'verified' => true,
        'expires_at' => now()->addMinutes(30)->timestamp]];
});

test('request submission waiting list cancellation review approval tracking and notifications work together', function () {
    $approved = ($this->makeBooking)();
    $this->withSession($this->proof)->post(route('reservation.store'), $this->payload)->assertSessionHasNoErrors();
    $waiting = Reservation::where('contact_email', 'waiting@example.com')->sole();
    expect($waiting->status)->toBe('waiting_list');
    Mail::assertSent(\App\Mail\ReservationSubmitted::class, fn ($mail) => str_contains($mail->render(), 'Status: Waiting List.') && str_contains($mail->subjectLine, 'Waiting List'));
    $this->get(route('reservation.track', ['reference' => $waiting->reference_number]))->assertOk()->assertSee('waiting-list-notice')->assertSee('Your request is on the waiting list');
    $this->actingAs($this->admin)->get(route('admin.reservations.waiting-list'))->assertOk()->assertSee($waiting->reference_number);
    $this->actingAs($this->admin)->patch(route('admin.reservations.approve', $waiting))->assertSessionHasErrors('reservation_date');
    $this->patch(route('admin.reservations.cancel', $approved), ['cancellation_reason' => 'Event withdrawn.'])->assertSessionHasNoErrors();
    $this->get(route('admin.reservations.show', $approved))->assertOk()->assertSee('Review '.$waiting->reference_number);
    $this->getJson(route('reservation.availability', ['date' => $this->date]))->assertExactJson([]);
    $this->patch(route('admin.reservations.approve', $waiting))->assertSessionHasNoErrors();
    expect($waiting->refresh()->status)->toBe('approved');
    Mail::assertSent(ReservationCancelled::class);
    Mail::assertSent(ReservationApproved::class, fn ($mail) => $mail->reservation->id === $waiting->id);
    expect($waiting->notifications()->first()->data['mail_status'])->toBe('sent');
    $this->getJson(route('reservation.availability', ['date' => $this->date]))->assertJsonCount(1);
    $other = ($this->makeBooking)(['status' => 'waiting_list']);
    $this->patch(route('admin.reservations.approve', $other))->assertSessionHasErrors('reservation_date');
    expect(Reservation::where('status', 'approved')->count())->toBe(1);
});

test('automatic waiting list never bypasses closures operating hours or approval daily limit', function () {
    ScheduleBlock::create(['facility_id' => $this->gym->id, 'created_by' => $this->admin->id,
        'starts_on' => $this->date, 'ends_on' => $this->date, 'title' => 'Closed']);
    $this->withSession($this->proof)->post(route('reservation.store'), $this->payload)->assertSessionHasErrors('reservation_date');
    ScheduleBlock::query()->delete();
    $this->withSession($this->proof)->post(route('reservation.store'), [...$this->payload, 'start_time' => '05:00', 'end_time' => '07:00'])
        ->assertSessionHasErrors('start_time');
    config(['gym.reservation.daily_limit' => 1]);
    ($this->makeBooking)();
    $waiting = ($this->makeBooking)(['status' => 'waiting_list', 'start_time' => '12:00', 'end_time' => '14:00']);
    $this->actingAs($this->admin)->patch(route('admin.reservations.approve', $waiting))->assertSessionHasErrors('reservation_date');
    expect($waiting->refresh()->status)->toBe('waiting_list');
});

test('automatic waiting request is saved and popup reports failed email delivery honestly', function () {
    ($this->makeBooking)();
    $this->get(route('reservation.create'))->assertOk()->assertDontSee('name="join_waiting_list"', false);
    Mail::shouldReceive('mailer')->with('smtp')->once()->andThrow(new RuntimeException('SMTP unavailable'));
    $response = $this->withSession($this->proof)->post(route('reservation.store'), $this->payload)
        ->assertSessionHasNoErrors()->assertSessionHas('reservation_email_sent', false);
    expect(Reservation::where('contact_email', 'waiting@example.com')->sole()->status)->toBe('waiting_list');
    $this->followRedirects($response)->assertOk()->assertSee('waiting-list-notice')->assertSee('email could not be sent');
});

test('administrator can cancel an active pending request from its existing detail page', function () {
    $booking = ($this->makeBooking)(['status' => 'new']);
    $this->actingAs($this->admin)->get(route('admin.reservations.show', $booking))->assertOk()->assertSee(route('admin.reservations.cancel', $booking), false);
    $this->patch(route('admin.reservations.cancel', $booking), ['cancellation_reason' => 'Requestor contacted the office.'])->assertSessionHasNoErrors();
    expect($booking->refresh()->status)->toBe('cancelled')->and($booking->cancelled_by)->toBe($this->admin->id);
});

test('administrator completes an ended event and reports expose saved actual attendance', function () {
    $booking = ($this->makeBooking)(['reservation_date' => now()->subDay()->toDateString()]);
    $this->actingAs($this->admin)->post(route('admin.reservations.outcome', $booking), [
        'outcome' => 'completed', 'actual_attendees' => 73, 'remarks' => 'Verified attendance register.', 'revision' => 0,
    ])->assertSessionHasNoErrors();
    $filters = ['report_type' => 'reservation', 'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->toDateString()];
    $this->get(route('admin.reports.index', $filters))->assertOk()->assertSee('73')->assertSee('Completed');
    $data = app(ReservationReportService::class)->reportData($filters);
    expect($data['metrics']['Completed'])->toBe(1)->and($data['metrics']['Recorded actual attendees'])->toBe(73)
        ->and(iterator_to_array(app(ReservationReportService::class)->exportRows($data))[0][8])->toBe(73);
    $pdf = $this->get(route('admin.reports.pdf', $filters))->assertOk()->assertDownload();
    expect(file_get_contents($pdf->baseResponse->getFile()->getPathname()))->toStartWith('%PDF-')->toContain('%%EOF');
    unlink($pdf->baseResponse->getFile()->getPathname());
});

test('PDF failures preserve filters and return an actionable message', function () {
    $this->mock(\App\Services\AdminReportExportService::class)->shouldReceive('pdf')->once()->andThrow(new RuntimeException('Simulated missing font'));
    $filters = ['report_type' => 'reservation', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31'];
    $this->actingAs($this->admin)->get(route('admin.reports.pdf', $filters))
        ->assertRedirect(route('admin.reports.index', $filters))->assertSessionHasErrors('export');
});

test('PDF content includes filtered reservation records and excludes other periods', function () {
    ($this->makeBooking)(['reference_number' => 'PDF-INCLUDED']);
    ($this->makeBooking)(['reference_number' => 'PDF-EXCLUDED', 'reservation_date' => now()->addMonth()->toDateString()]);
    $filters = ['report_type' => 'reservation', 'date_from' => $this->date, 'date_to' => $this->date];
    $response = $this->actingAs($this->admin)->get(route('admin.reports.pdf', $filters))->assertOk()->assertDownload();
    $path = $response->baseResponse->getFile()->getPathname();
    $contents = file_get_contents($path);
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $contents, $streams);
    $decoded = '';
    foreach ($streams[1] as $stream) $decoded .= @gzuncompress($stream) ?: '';
    expect($decoded)->toContain(mb_convert_encoding('PDF-INCLUDED', 'UTF-16BE', 'UTF-8'))
        ->not->toContain(mb_convert_encoding('PDF-EXCLUDED', 'UTF-16BE', 'UTF-8'));
    unlink($path);
});

test('failed cancellation email preserves cancellation and exposes delivery failure to the administrator', function () {
    $booking = ($this->makeBooking)();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));
    $this->actingAs($this->admin)->patch(route('admin.reservations.cancel', $booking), ['cancellation_reason' => 'Withdrawn.'])
        ->assertSessionHasNoErrors();
    expect($booking->refresh()->status)->toBe('cancelled')->and($booking->notifications()->first()->data['mail_status'])->toBe('failed');
    $this->get(route('admin.reservations.show', $booking))->assertOk()->assertSee('email could not be sent');
});
