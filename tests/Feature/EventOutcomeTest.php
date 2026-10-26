<?php

use App\Models\{AuditLog, Facility, Reservation, ReservationEventOutcome, User};
use App\Services\{DashboardAnalyticsService, EventOutcomeAnalyticsService};

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 18)->setTime(18, 0));
    $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
    $this->staff = User::factory()->create(['role' => 'staff', 'status' => 'approved']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->make = function (array $values = []) {
        return Reservation::create(array_replace(['reference_number' => 'OUTCOME-'.strtoupper(uniqid()), 'facility_id' => $this->gym->id,
            'reservation_type' => 'internal', 'event_name' => 'Sports event', 'purpose' => 'Sports', 'contact_person' => 'Requestor',
            'contact_email' => 'requestor@example.com', 'contact_number' => '09123456789', 'expected_attendees' => 100,
            'reservation_date' => '2026-10-18', 'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved'], $values));
    };
    $this->booking = ($this->make)();
    $this->data = ['outcome' => 'completed', 'actual_attendees' => 80, 'remarks' => 'Staff confirmed that the event took place.', 'revision' => 0];
    $this->actingAs($this->admin);
});

test('approved staff can record a completed event with actual attendance and an audit trail', function () {
    $this->actingAs($this->staff)->post(route('staff.reservations.outcome', $this->booking), $this->data)->assertSessionHasNoErrors();
    $result = $this->booking->eventOutcome()->firstOrFail();
    expect($this->booking->refresh()->status)->toBe('completed')->and($this->booking->completed_at)->not->toBeNull()
        ->and($result->actual_attendees)->toBe(80)->and($result->expected_attendees)->toBe(100)
        ->and($result->recorded_by)->toBe($this->staff->id)->and($result->revision)->toBe(1);
    expect(AuditLog::where('action', 'reservation.event_outcome_recorded')->exists())->toBeTrue();
    $this->get(route('staff.reservations.show', $this->booking))->assertOk()->assertSee('80 actual / 100 expected attendees.');
});

test('no show is a separate outcome with zero attendees and no cancellation timestamp', function () {
    $this->post(route('admin.reservations.outcome', $this->booking), [...$this->data, 'outcome' => 'no_show', 'actual_attendees' => 0])->assertSessionHasNoErrors();
    expect($this->booking->refresh()->status)->toBe('no_show')->and($this->booking->completed_at)->toBeNull()
        ->and($this->booking->cancelled_at)->toBeNull()->and($this->booking->eventOutcome->actual_attendees)->toBe(0);
    $this->getJson(route('reservation.track', ['reference' => $this->booking->reference_number]))->assertOk()->assertJsonPath('status', 'no_show');
    $this->get(route('admin.reservations.show', $this->booking))->assertOk()->assertSee('No-show');
});

test('attendance preview and exports include recorded outcomes and optional remarks', function () {
    $this->post(route('admin.reservations.outcome', $this->booking), [...$this->data, 'remarks' => null])->assertSessionHasNoErrors();
    $missing = ($this->make)(['status' => 'completed']);
    $noShow = ($this->make)();
    $this->post(route('admin.reservations.outcome', $noShow), [...$this->data, 'outcome' => 'no_show', 'actual_attendees' => 0])->assertSessionHasNoErrors();
    $filters = ['report_type' => 'attendance', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31'];
    $this->get(route('admin.reports.index', $filters))->assertOk()
        ->assertSee('Attendance &amp; No-show Report', false)->assertSee('Attended / Completed')->assertSee('Recorded by')
        ->assertSee($noShow->reference_number)->assertDontSee($missing->reference_number)
        ->assertViewHas('data', fn ($data) => $data['total'] === 2 && $data['metrics']['Actual attendees'] === 80);
    $excel = $this->get(route('admin.reports.excel', $filters))->assertOk()->assertDownload();
    $path = $excel->baseResponse->getFile()->getPathname();
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    expect($book->getSheetByName('Records')->getCell('L1')->getValue())->toBe('Remarks');
    $book->disconnectWorksheets();
    unlink($path);
    $pdf = $this->get(route('admin.reports.pdf', $filters))->assertOk()->assertDownload();
    unlink($pdf->baseResponse->getFile()->getPathname());
});

test('future and ongoing events cannot be finalized even by an administrator', function ($date, $start, $end) {
    $this->booking->update(['reservation_date' => $date, 'start_time' => $start, 'end_time' => $end]);
    $this->post(route('admin.reservations.outcome', $this->booking), $this->data)->assertSessionHasErrors('outcome');
    expect(ReservationEventOutcome::count())->toBe(0)->and($this->booking->refresh()->status)->toBe('approved');
})->with([['2026-10-19', '09:00', '11:00'], ['2026-10-18', '17:00', '19:00'], ['2026-10-18', '17:00', '24:00'], ['2026-10-18', '17:00', '00:00']]);

test('midnight events become recordable at the next day boundary', function () {
    $this->booking->update(['start_time' => '17:00', 'end_time' => '24:00']);
    $this->travelTo(now()->addDay()->startOfDay());
    $this->post(route('admin.reservations.outcome', $this->booking), $this->data)->assertSessionHasNoErrors();
    expect($this->booking->refresh()->status)->toBe('completed');
});

test('unapproved cancelled and rejected requests cannot become no shows', function ($status) {
    $this->booking->update(['status' => $status]);
    $this->post(route('admin.reservations.outcome', $this->booking), [...$this->data, 'outcome' => 'no_show', 'actual_attendees' => 0])->assertSessionHasErrors('outcome');
    expect($this->booking->refresh()->status)->toBe($status)->and(ReservationEventOutcome::count())->toBe(0);
})->with(['new', 'validated', 'waiting_list', 'pencil', 'cancelled', 'rejected']);

test('actual attendance is bounded and must agree with the recorded outcome', function ($values) {
    $this->post(route('admin.reservations.outcome', $this->booking), array_replace($this->data, $values))->assertSessionHasErrors('actual_attendees');
    expect(ReservationEventOutcome::count())->toBe(0);
})->with([
    [['actual_attendees' => 0]], [['actual_attendees' => -1]], [['actual_attendees' => 2001]], [['actual_attendees' => '8.5']],
    [['outcome' => 'no_show', 'actual_attendees' => 80]],
]);

test('actual attendance below the expected booking minimum remains valid', function () {
    $this->post(route('admin.reservations.outcome', $this->booking), [...$this->data, 'actual_attendees' => 1])->assertSessionHasNoErrors();
    expect($this->booking->eventOutcome->actual_attendees)->toBe(1);
});

test('only administrators can correct an outcome and must provide a reason and current revision', function () {
    $route = route('admin.reservations.outcome', $this->booking);
    $this->post($route, $this->data)->assertSessionHasNoErrors();
    $changes = [...$this->data, 'outcome' => 'no_show', 'actual_attendees' => 0, 'revision' => 1];
    $this->actingAs($this->staff)->post(route('staff.reservations.outcome', $this->booking), $changes)->assertForbidden();
    $this->actingAs($this->admin)->post($route, $changes)->assertSessionHasErrors('correction_reason');
    $this->post($route, [...$changes, 'revision' => 0, 'correction_reason' => 'Verified the attendance log.'])->assertSessionHasErrors('outcome');
    $this->post($route, [...$changes, 'correction_reason' => 'Verified the attendance log.'])->assertSessionHasNoErrors();
    expect($this->booking->refresh()->status)->toBe('no_show')->and($this->booking->eventOutcome->revision)->toBe(2);
    expect(AuditLog::where('action', 'reservation.event_outcome_corrected')->exists())->toBeTrue();
});

test('unapproved staff guests and requestors cannot record outcomes', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff', 'status' => 'pending']))
        ->post(route('staff.reservations.outcome', $this->booking), $this->data)->assertForbidden();
    auth()->logout();
    $this->post(route('admin.reservations.outcome', $this->booking), $this->data)->assertRedirect();
    expect(ReservationEventOutcome::count())->toBe(0);
});

test('no show cannot be rescheduled cancelled or sent a replacement proposal', function () {
    $this->post(route('admin.reservations.outcome', $this->booking), [...$this->data, 'outcome' => 'no_show', 'actual_attendees' => 0])->assertSessionHasNoErrors();
    $this->postJson(route('admin.reservations.reschedule-preview', $this->booking), ['reservation_date' => '2026-10-25', 'start_time' => '09:00', 'reason' => 'Move', 'send_proposal' => 1])->assertUnprocessable();
    $this->patch(route('admin.reservations.cancel', $this->booking), ['cancellation_reason' => 'Cancel'])->assertSessionHasErrors();
    expect($this->booking->refresh()->status)->toBe('no_show');
});

test('BI reports only recorded ended events and exposes missing outcome coverage', function () {
    $this->post(route('admin.reservations.outcome', $this->booking), $this->data)->assertSessionHasNoErrors();
    $noShow = ($this->make)();
    $this->post(route('admin.reservations.outcome', $noShow), [...$this->data, 'outcome' => 'no_show', 'actual_attendees' => 0])->assertSessionHasNoErrors();
    ($this->make)();
    ($this->make)(['status' => 'completed']); // Legacy record is not verified attendance.
    ($this->make)(['status' => 'cancelled']);
    ($this->make)(['status' => 'new']);
    ($this->make)(['reservation_date' => '2026-10-25']);
    $scope = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'grouping' => 'monthly'];
    $result = app(EventOutcomeAnalyticsService::class)->summarize($scope, app(DashboardAnalyticsService::class))['eventResults'];
    expect($result['completionRate'])->toBe(50.0)->and($result['noShowRate'])->toBe(50.0)
        ->and($result['coverage'])->toBe(50.0)->and($result['attendanceRate'])->toBe(80.0)
        ->and($result['actual'])->toBe(80)->and($result['expected'])->toBe(100)->and($result['unknown'])->toHaveCount(2);
    $performance = app(\App\Services\DashboardPerformanceService::class)->summarize($scope, app(DashboardAnalyticsService::class));
    expect($performance['performance']['approvalRate'])->toBe(71.4);
    $this->get(route('admin.dashboard', $scope))->assertOk()->assertSee('Event outcomes and actual attendance');
    $csv = $this->get(route('admin.dashboard', [...$scope, 'export' => 'csv']))->assertOk()->streamedContent();
    expect($csv)->toContain('Recorded no-show rate (%)', '80');
});

test('BI empty samples report N/A and filters exclude unrelated outcomes', function () {
    $scope = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'grouping' => 'monthly'];
    $result = app(EventOutcomeAnalyticsService::class)->summarize($scope, app(DashboardAnalyticsService::class))['eventResults'];
    expect($result['completionRate'])->toBeNull()->and($result['attendanceRate'])->toBeNull()->and($result['coverage'])->toBe(0.0);
    $this->post(route('admin.reservations.outcome', $this->booking), $this->data)->assertSessionHasNoErrors();
    $result = app(EventOutcomeAnalyticsService::class)->summarize([...$scope, 'status' => 'cancelled'], app(DashboardAnalyticsService::class))['eventResults'];
    expect($result['eligible'])->toBe(0)->and($result['completionRate'])->toBeNull();
});
