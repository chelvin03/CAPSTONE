<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsReport;
use App\Models\Reservation;
use App\Models\User;
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
    public function index(Request $request): View
    {
        $type = (string) $request->query('type', '');
        abort_unless($type === '' || in_array($type, ['weekly', 'monthly', 'annual'], true), 422);

        $reports = AnalyticsReport::with('submitter')
            ->when($type !== '', fn ($query) => $query->where('report_type', $type))
            ->latest('submitted_at')->paginate(15)->withQueryString();

        $total = Reservation::count();
        $cancelled = Reservation::where('status', 'cancelled')->count();
        return view('admin.reports.index', compact('reports', 'type') + [
            'reservationTotal' => $total,
            'cancellationRate' => $total ? (int) round($cancelled / $total * 100) : 0,
            'priorityOverrides' => Reservation::whereNotNull('priority_number')->count(),
            'peakDate' => Reservation::selectRaw('reservation_date, COUNT(*) as total')->groupBy('reservation_date')->orderByDesc('total')->first(),
            'reportUsers' => User::whereIn('role', ['admin', 'staff'])->orderBy('first_name')->get(),
            'categories' => Reservation::whereNotNull('reservation_type')->distinct()->orderBy('reservation_type')->pluck('reservation_type'),
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
        $headers = ['Reference','Date','Start','End','Event','Category','Facility','Requestor','Email','Attendees','Status','Priority','Created By'];
        $sheet->fromArray($headers, null, 'A1');
        $row = 2;
        foreach ($records as $record) {
            $sheet->fromArray([$record->reference_number, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($record->reservation_date), substr($record->start_time,0,5), substr($record->end_time,0,5), $record->event_name, $record->reservation_type, $record->facility?->facility_name ?? 'Unassigned', $record->contact_person, $record->contact_email, $record->expected_attendees, ucwords(str_replace('_',' ',$record->status)), $record->priority_number ? 'Yes' : 'No', $record->user?->full_name ?? 'Public Requestor'], null, 'A'.$row++);
        }
        $lastRow = max(2, $row - 1);
        $sheet->setAutoFilter("A1:M{$lastRow}"); $sheet->freezePane('A2');
        $sheet->getStyle('A1:M1')->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']], 'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1E3A8A']], 'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
        $sheet->getStyle("B2:B{$lastRow}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle("J2:J{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A','M') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->getColumnDimension('E')->setWidth(30); $sheet->getColumnDimension('H')->setWidth(24); $sheet->getColumnDimension('I')->setWidth(28);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(.4)->setBottom(.4)->setLeft(.3)->setRight(.3);
        $sheet->getHeaderFooter()->setOddFooter('&LMCST Gym Reservation System&CPage &P of &N&RGenerated '.now()->format('Y-m-d'));

        $path = tempnam(sys_get_temp_dir(), 'mcst-report-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path); $spreadsheet->disconnectWorksheets();
        return response()->download($path, 'mcst-reservation-report-'.now()->format('Y-m-d-His').'.xlsx')->deleteFileAfterSend(true);
    }

    public function word(Request $request, ReservationReportService $service): BinaryFileResponse
    {
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
        foreach (['Reference','Date / Time','Event','Facility','Requestor','Attendees','Status'] as $header) $table->addCell(1500,['bgColor'=>'1E3A8A'])->addText($header,['bold'=>true,'color'=>'FFFFFF']);
        foreach ($records as $record) { $table->addRow(); foreach ([$record->reference_number,$record->reservation_date->format('Y-m-d').' '.substr($record->start_time,0,5).'-'.substr($record->end_time,0,5),$record->event_name,$record->facility?->facility_name ?? 'Unassigned',$record->contact_person,(string)$record->expected_attendees,ucwords(str_replace('_',' ',$record->status))] as $value) $table->addCell(1500)->addText((string)$value); }
        if ($records->isEmpty()) { $table->addRow(); $table->addCell(10500,['gridSpan'=>7])->addText('No records matched the selected criteria.', ['italic'=>true,'color'=>'64748B']); }
        $footer = $section->addFooter(); $footer->addPreserveText('MCST Gym Reservation System | Page {PAGE} of {NUMPAGES}', ['size'=>8,'color'=>'64748B'], ['alignment'=>'center']);
        $path = tempnam(sys_get_temp_dir(), 'mcst-report-').'.docx'; WordIOFactory::createWriter($word, 'Word2007')->save($path);
        return response()->download($path, 'mcst-reservation-report-'.now()->format('Y-m-d-His').'.docx')->deleteFileAfterSend(true);
    }
}
