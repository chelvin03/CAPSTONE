<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsReport;
use App\Models\Reservation;
use App\Services\ReservationReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

class ReportController extends Controller
{
    public function index(Request $request, ReservationReportService $service): View
    {
        $defaults = ['report_type' => 'reservation', 'date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->endOfMonth()->toDateString()];
        $filters = $request->hasAny(['report_type', 'date_from', 'date_to']) ? $service->reportFilters($request) : $defaults;
        $data = $request->hasAny(['report_type', 'date_from', 'date_to']) ? $service->reportData($filters) : null;
        return view('admin.reports.index', [
            'types' => ReservationReportService::TYPES, 'filters' => $filters, 'defaults' => $defaults, 'data' => $data,
            'records' => $data && $data['rows'] === null ? $service->reportQuery($filters)->paginate(25)->withQueryString() : null,
            'service' => $service,
        ]);
    }

    public function show(AnalyticsReport $report): View
    {
        return view('admin.reports.show', ['report' => $report->load('submitter')]);
    }

    public function export(): StreamedResponse
    {
        $reservations = Reservation::with('facility')->orderBy('reservation_date')->get();
        return response()->streamDownload(function () use ($reservations): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Reference','Date','Facility','Event','Status','Attendees','Priority Override']);
            foreach ($reservations as $r) fputcsv($out, [$r->reference_number,$r->reservation_date->format('Y-m-d'),$r->facility?->facility_name,$r->event_name,$r->status,$r->expected_attendees,$r->priority_number ? 'Yes' : 'No']);
            fclose($out);
        }, 'admin-reservation-analytics-'.now()->format('Y-m-d').'.csv', ['Content-Type'=>'text/csv']);
    }

    public function excel(Request $request, ReservationReportService $service): BinaryFileResponse
    {
        if (array_key_exists((string) $request->query('report_type'), ReservationReportService::TYPES)) {
            return app(\App\Services\AdminReportExportService::class)->excel($service->reportData($service->reportFilters($request)), $service);
        }
        $filters = $service->filters($request);
        $records = $service->records($filters);
        $summary = $service->summary($records);
        $spreadsheet = new Spreadsheet();
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Summary');
        $summarySheet->fromArray([
            ['MCST Gym Reservation Report'],
            ['Generated', now()->format('F d, Y g:i A')],
            ['Report Type', ucwords(str_replace('_', ' ', $filters['report_type']))],
            ['Total Reservations', $summary['total']],
            ['Approved', $summary['approved']],
            ['Cancelled', $summary['cancelled']],
            ['Completed', $summary['completed']],
            ['Expected Attendees', $summary['attendees']],
            ['Cancellation Rate', $summary['cancellation_rate']],
        ], null, 'A1');
        $summarySheet->mergeCells('A1:D1');
        $summarySheet->getStyle('A1:D1')->applyFromArray(['font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '136BE7']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
        $summarySheet->getStyle('A2:A9')->getFont()->setBold(true);
        $summarySheet->getStyle('B9')->getNumberFormat()->setFormatCode('0.0%');
        $summarySheet->getColumnDimension('A')->setWidth(24); $summarySheet->getColumnDimension('B')->setWidth(30);
        $summarySheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);

        $sheet = $spreadsheet->createSheet(); $sheet->setTitle('Records');
        $headers = ['Reference','Date','Start','End','Event','Category','Facility','Attendees','Status','Priority'];
        $sheet->fromArray($headers, null, 'A1');
        $row = 2;
        foreach ($records as $record) {
            $sheet->fromArray([$record->reference_number, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($record->reservation_date), substr($record->start_time,0,5), substr($record->end_time,0,5), $record->event_name, $record->reservation_type, $record->facility?->facility_name ?? 'Unassigned', $record->expected_attendees, ucwords(str_replace('_',' ',$record->status)), $record->priority_number ? 'Yes' : 'No'], null, 'A'.$row++);
            foreach (['A' => $record->reference_number, 'E' => $record->event_name, 'F' => $record->reservation_type, 'G' => $record->facility?->facility_name ?? 'Unassigned'] as $column => $value) {
                $sheet->setCellValueExplicit($column.($row - 1), $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }
        $lastRow = max(2, $row - 1);
        $sheet->setAutoFilter("A1:J{$lastRow}"); $sheet->freezePane('A2');
        $sheet->getStyle('A1:J1')->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']], 'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1E3A8A']], 'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
        $sheet->getStyle("B2:B{$lastRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle("H2:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A','J') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->getColumnDimension('E')->setWidth(30);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(.4)->setBottom(.4)->setLeft(.3)->setRight(.3);
        $sheet->getHeaderFooter()->setOddFooter('&LMCST Gym Reservation System&CPage &P of &N&RGenerated '.now()->format('Y-m-d'));

        $path = tempnam(sys_get_temp_dir(), 'mcst-report-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path); $spreadsheet->disconnectWorksheets();
        return response()->download($path, 'mcst-reservation-report-'.now()->format('Y-m-d-His').'.xlsx')->deleteFileAfterSend(true);
    }

    public function pdf(Request $request, ReservationReportService $service): BinaryFileResponse
    {
        return app(\App\Services\AdminReportExportService::class)->pdf($service->reportData($service->reportFilters($request)), $service);
    }

    public function word(Request $request, ReservationReportService $service): BinaryFileResponse
    {
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
        $filters = $service->filters($request); $records = $service->records($filters); $summary = $service->summary($records);
        $word = new PhpWord();
        $word->setDefaultFontName('Arial'); $word->setDefaultFontSize(9);
        $section = $word->addSection(['orientation'=>'landscape','marginTop'=>600,'marginBottom'=>600,'marginLeft'=>600,'marginRight'=>600]);
        $section->addTitle('MCST Gym Reservation Report', 1);
        $section->addText('Generated: '.now()->format('F d, Y g:i A').' | Report: '.ucwords(str_replace('_',' ',$filters['report_type'])), ['color'=>'475569']);
        $summaryTable = $section->addTable(['borderSize'=>4,'borderColor'=>'CBD5E1','cellMargin'=>100]);
        foreach ([['Total Reservations',$summary['total']],['Approved',$summary['approved']],['Cancelled',$summary['cancelled']],['Completed',$summary['completed']],['Expected Attendees',$summary['attendees']],['Cancellation Rate',number_format($summary['cancellation_rate']*100,1).'%']] as [$label,$value]) { $summaryTable->addRow(); $summaryTable->addCell(2400,['bgColor'=>'EAF2FF'])->addText($label,['bold'=>true]); $summaryTable->addCell(1400)->addText((string)$value); }
        $section->addTextBreak(); $section->addTitle('Reservation Records', 2);
        $table = $section->addTable(['borderSize'=>4,'borderColor'=>'CBD5E1','cellMargin'=>70]);
        $table->addRow(null, ['tblHeader'=>true]);
        foreach (['Reference','Date / Time','Event','Facility','Attendees','Status'] as $header) $table->addCell(1750,['bgColor'=>'1E3A8A'])->addText($header,['bold'=>true,'color'=>'FFFFFF']);
        foreach ($records as $record) { $table->addRow(); foreach ([$record->reference_number,$record->reservation_date->format('Y-m-d').' '.substr($record->start_time,0,5).'-'.substr($record->end_time,0,5),$record->event_name,$record->facility?->facility_name ?? 'Unassigned',(string)$record->expected_attendees,ucwords(str_replace('_',' ',$record->status))] as $value) $table->addCell(1750)->addText((string)$value); }
        if ($records->isEmpty()) { $table->addRow(); $table->addCell(10500,['gridSpan'=>6])->addText('No records matched the selected criteria.', ['italic'=>true,'color'=>'64748B']); }
        $footer = $section->addFooter(); $footer->addPreserveText('MCST Gym Reservation System | Page {PAGE} of {NUMPAGES}', ['size'=>8,'color'=>'64748B'], ['alignment'=>'center']);
        $path = tempnam(sys_get_temp_dir(), 'mcst-report-').'.docx'; WordIOFactory::createWriter($word, 'Word2007')->save($path);
        return response()->download($path, 'mcst-reservation-report-'.now()->format('Y-m-d-His').'.docx')->deleteFileAfterSend(true);
    }
}
