<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Services\ReservationReportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

function adminReportFixture(array $overrides = []): Reservation
{
    $facility = Facility::firstOrCreate(['facility_name' => 'Reports Gym'], ['capacity' => 200, 'status' => 'available']);
    return Reservation::create(array_merge([
        'reference_number' => 'REPORT-'.fake()->unique()->numerify('########'),
        'facility_id' => $facility->id, 'user_id' => User::first()->id,
        'reservation_type' => 'school', 'event_type' => 'Sports', 'event_name' => 'Gym event',
        'purpose' => 'Testing', 'contact_person' => 'Private Person', 'contact_email' => 'private@example.com',
        'contact_number' => '09123456789', 'expected_attendees' => 20,
        'reservation_date' => '2026-09-01', 'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved',
    ], $overrides));
}

test('all five previews and both exports match real reservation date totals', function (string $type) {
    $admin = User::factory()->create(['role' => 'admin']);
    adminReportFixture(['reference_number' => 'BOUNDARY-START', 'created_at' => '2025-01-01', 'event_name' => '=SUM(1,2)']);
    adminReportFixture(['reference_number' => 'BOUNDARY-END', 'reservation_date' => '2026-09-30', 'status' => 'cancelled', 'event_type' => 'Ceremony']);
    adminReportFixture(['reference_number' => 'OUTSIDE', 'reservation_date' => '2026-10-01']);
    $filters = ['report_type' => $type, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'];
    $expected = in_array($type, ['utilization', 'cancellation']) ? 1 : 2;
    $this->actingAs($admin)->get(route('admin.reports.index', $filters))->assertOk()
        ->assertViewHas('data', fn ($data) => $data['total'] === $expected)
        ->assertDontSee('private@example.com')->assertDontSee('Private Person')->assertDontSee('OUTSIDE');
    $excel = $this->get(route('admin.reports.excel', $filters))->assertOk()->assertDownload();
    $path = $excel->baseResponse->getFile()->getPathname();
    $book = IOFactory::load($path);
    expect($book->getSheetByName('Summary')->getCell('B6')->getValue())->toBe($expected)
        ->and($book->getSheetByName('Summary')->getCell('B4')->getValue())->toBe('2026-09-01');
    if ($type === 'reservation') {
        expect($book->getSheetByName('Records')->getCell('B2')->getDataType())->toBe('s')
            ->and($book->getSheetByName('Records')->getCell('B2')->getValue())->toBe('=SUM(1,2)');
    }
    $book->disconnectWorksheets();
    unlink($path);
    $pdf = $this->get(route('admin.reports.pdf', $filters))->assertOk()->assertDownload();
    $path = $pdf->baseResponse->getFile()->getPathname();
    $contents = file_get_contents($path);
    expect($contents)->toStartWith('%PDF-')->toContain('%%EOF')->toContain('/Type /Pages');
    unlink($path);
})->with(array_keys(ReservationReportService::TYPES));

test('reports validate required dates invalid dates ranges and report types on all endpoints', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    foreach (['index', 'excel', 'pdf'] as $endpoint) {
        foreach ([
            [['report_type' => 'reservation'], 'date_from'],
            [['report_type' => 'reservation', 'date_from' => '2026-02-30', 'date_to' => '2026-03-01'], 'date_from'],
            [['report_type' => 'reservation', 'date_from' => '2026-09-30', 'date_to' => '2026-09-01'], 'date_to'],
            [['report_type' => 'inventory', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'], 'report_type'],
        ] as [$filters, $error]) $this->get(route('admin.reports.'.$endpoint, $filters))->assertSessionHasErrors($error);
    }
});

test('empty previews and exports are clear for every report', function (string $type) {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $filters = ['report_type' => $type, 'date_from' => '2035-01-01', 'date_to' => '2035-01-31'];
    $this->get(route('admin.reports.index', $filters))->assertOk()->assertSee('No reservations match the selected period.');
    foreach (['excel', 'pdf'] as $extension) {
        $response = $this->get(route('admin.reports.'.$extension, $filters))->assertOk()->assertDownload();
        unlink($response->baseResponse->getFile()->getPathname());
    }
})->with(array_keys(ReservationReportService::TYPES));

test('status and event counts preserve real status and event type values', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    foreach (['new', 'validated', 'approved', 'rejected', 'waiting_list', 'cancelled', 'completed', 'pending'] as $status) adminReportFixture(['status' => $status]);
    $filters = ['report_type' => 'status', 'date_from' => '2026-09-01', 'date_to' => '2026-09-01'];
    $this->get(route('admin.reports.index', $filters))->assertViewHas('data', fn ($data) => $data['rows']->sum(fn ($row) => $row[1]) === 8 && $data['rows']->contains(['Waiting List', 1]));
    $filters['report_type'] = 'event_type';
    $this->get(route('admin.reports.index', $filters))->assertViewHas('data', fn ($data) => $data['rows']->all() === [['Sports', 8]]);
    adminReportFixture(['event_type' => null]);
    adminReportFixture(['event_type' => '']);
    $this->get(route('admin.reports.index', $filters))->assertViewHas('data', fn ($data) => $data['rows']->contains(['Not specified', 2]) && $data['total'] === 10);
});

test('utilization shares dashboard merged hours closures and unavailable capacity', function () {
    config(['gym.reservation.opening_time' => '08:00', 'gym.reservation.closing_time' => '12:00']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $record = adminReportFixture();
    adminReportFixture(['start_time' => '10:00', 'end_time' => '12:00']);
    adminReportFixture(['status' => 'completed']);
    ScheduleBlock::create(['facility_id' => $record->facility_id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-01', 'start_time' => '11:00', 'end_time' => '12:00', 'title' => 'Closure', 'reason' => 'Testing', 'created_by' => User::first()->id]);
    $filters = ['report_type' => 'utilization', 'date_from' => '2026-09-01', 'date_to' => '2026-09-01'];
    $this->get(route('admin.reports.index', $filters))->assertViewHas('data', fn ($data) => $data['metrics']['Approved booked hours'] === 2.0 && $data['metrics']['Bookable hours'] === 3.0 && $data['metrics']['Utilization'] === '66.7%');
    config(['gym.reservation.opening_time' => null]);
    $this->get(route('admin.reports.index', $filters))->assertSee('Unavailable');
});

test('quick reports use the current month and admin authorization is retained', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-05'));
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.reports.index'))->assertOk()->assertSee('2026-10-01 to 2026-10-31');
    $this->actingAs(User::factory()->create(['role' => 'staff']));
    foreach (['index', 'excel', 'pdf', 'word', 'export'] as $endpoint) $this->get(route('admin.reports.'.$endpoint))->assertForbidden();
    auth()->logout();
    $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
});

test('exports include records beyond pagination and database chunks and escape event content', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $record = adminReportFixture(['event_name' => '<script>alert("event")</script>']);
    $attributes = $record->getAttributes();
    unset($attributes['id']);
    $batch = [];
    for ($index = 1; $index <= 600; $index++) $batch[] = array_merge($attributes, ['reference_number' => 'BULK-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT)]);
    Reservation::insert($batch);
    $filters = ['report_type' => 'reservation', 'date_from' => '2026-09-01', 'date_to' => '2026-09-01'];
    $this->get(route('admin.reports.index', $filters))->assertOk()
        ->assertViewHas('records', fn ($records) => $records->total() === 601 && $records->count() === 25)
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("event")</script>', false);
    $response = $this->get(route('admin.reports.excel', $filters))->assertOk()->assertDownload();
    $path = $response->baseResponse->getFile()->getPathname();
    $book = IOFactory::load($path);
    expect($book->getSheetByName('Records')->getHighestDataRow())->toBe(602)
        ->and($book->getSheetByName('Records')->getCell('A602')->getValue())->toBe('BULK-0600');
    $book->disconnectWorksheets();
    unlink($path);
    $response = $this->get(route('admin.reports.pdf', $filters))->assertOk()->assertDownload();
    $path = $response->baseResponse->getFile()->getPathname();
    expect(preg_match_all('/\/Type \/Page\b/', file_get_contents($path)))->toBeGreaterThan(1);
    unlink($path);
});
