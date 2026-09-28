<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

function reportReservation(User $user, Facility $facility, array $overrides): Reservation
{
    return Reservation::create(array_merge([
        'reference_number' => 'EXPORT-'.fake()->unique()->numerify('#####'),
        'user_id' => $user->id, 'facility_id' => $facility->id, 'reservation_type' => 'school',
        'event_name' => 'Export Test Event', 'purpose' => 'Report accuracy checking',
        'contact_person' => 'Report User', 'contact_email' => 'report@example.com', 'contact_number' => '09123456789',
        'expected_attendees' => 40, 'reservation_date' => '2026-08-15', 'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved',
    ], $overrides));
}

test('excel report applies filters and matches database totals without duplicates', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);
    $facility = Facility::create(['facility_name' => 'Report Gym', 'capacity' => 200, 'status' => 'available']);
    reportReservation($staff, $facility, ['reference_number'=>'EXPORT-INCLUDED','expected_attendees'=>80,'status'=>'approved']);
    reportReservation($staff, $facility, ['reference_number'=>'EXPORT-CANCELLED','status'=>'cancelled','reservation_date'=>'2026-08-20']);
    reportReservation($staff, $facility, ['reference_number'=>'EXPORT-OTHER-CATEGORY','status'=>'approved','reservation_type'=>'sports']);
    reportReservation($admin, $facility, ['reference_number'=>'EXPORT-OTHER-USER','status'=>'approved']);

    $response = $this->actingAs($admin)->get(route('admin.reports.excel', [
        'report_type'=>'reservation_detail','date_from'=>'2026-08-01','date_to'=>'2026-08-31','status'=>'approved','category'=>'school','user_id'=>$staff->id,
    ]))->assertOk()->assertDownload();

    $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());
    expect($workbook->getSheetByName('Summary')->getCell('B4')->getValue())->toBe(1)
        ->and($workbook->getSheetByName('Summary')->getCell('B8')->getValue())->toBe(80)
        ->and($workbook->getSheetByName('Records')->getHighestDataRow())->toBe(2)
        ->and($workbook->getSheetByName('Records')->getCell('A2')->getValue())->toBe('EXPORT-INCLUDED');
    $workbook->disconnectWorksheets();
});

test('word report opens as valid docx and contains filtered database data', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $facility = Facility::create(['facility_name' => 'Word Report Gym', 'capacity' => 100, 'status' => 'available']);
    reportReservation($admin, $facility, ['reference_number'=>'WORD-PRIORITY','priority_number'=>1]);
    reportReservation($admin, $facility, ['reference_number'=>'WORD-NORMAL','priority_number'=>null]);

    $response = $this->actingAs($admin)->get(route('admin.reports.word', ['report_type'=>'priority_history']))->assertOk()->assertDownload();
    $zip = new ZipArchive();
    expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
    $xml = $zip->getFromName('word/document.xml'); $zip->close();
    expect($xml)->toContain('WORD-PRIORITY')->not->toContain('WORD-NORMAL');
});

test('report generation handles invalid and empty criteria safely', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.reports.excel', ['report_type'=>'reservation_detail','date_from'=>'2026-09-01','date_to'=>'2026-08-01']))->assertSessionHasErrors('date_to');
    $this->get(route('admin.reports.word', ['report_type'=>'summary','date_from'=>'2035-01-01','date_to'=>'2035-01-31']))->assertOk()->assertDownload();
});
