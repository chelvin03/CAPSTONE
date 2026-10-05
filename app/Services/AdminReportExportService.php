<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminReportExportService
{
    public function excel(array $data, ReservationReportService $service): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'mcst-xlsx-');
        $cachePath = $path.'-cells';
        $files = new Filesystem();
        $previousCache = Settings::getCache();
        Settings::setCache(new Repository(new FileStore($files, $cachePath)));
        $book = new Spreadsheet();
        try {
            $summary = $book->getActiveSheet();
            $summary->setTitle('Summary');
            $summary->fromArray([
                ['MCST Gymnasium Reservation and Utilization System'],
                [$data['title']],
                ['Generated', $data['generatedAt']->format('Y-m-d H:i:s T')],
                ['Period start', $data['filters']['date_from']],
                ['Period end', $data['filters']['date_to']],
                ...array_map(fn ($label, $value) => [$label, $value], array_keys($data['metrics']), array_values($data['metrics'])),
                ['Calculation', $data['note']],
            ]);
            $summary->getColumnDimension('A')->setWidth(35);
            $summary->getColumnDimension('B')->setWidth(90);
            $summary->getStyle('B1:B20')->getAlignment()->setWrapText(true);
            $sheet = $book->createSheet();
            $sheet->setTitle('Records');
            $sheet->fromArray($data['headers']);
            $row = 2;
            $part = 1;
            foreach ($service->exportRows($data) as $values) {
                // Split very large reports before the Excel worksheet row limit.
                if ($row > 1000000) {
                    $sheet = $book->createSheet();
                    $sheet->setTitle('Records '.++$part);
                    $sheet->fromArray($data['headers']);
                    $row = 2;
                }
                foreach ($values as $column => $value) {
                    // Explicit strings prevent event names/references from becoming formulas.
                    $sheet->setCellValueExplicit([$column + 1, $row], $value, is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
                $row++;
            }
            if ($data['total'] === 0) $summary->setCellValue('A15', 'No reservations match the selected period.');
            foreach ($book->getAllSheets() as $sheet) {
                $sheet->getStyle('A1:'.$sheet->getHighestDataColumn().'1')->getFont()->setBold(true)->getColor()->setRGB('1E3A8A');
                if ($sheet->getTitle() !== 'Summary') {
                    $sheet->freezePane('A2');
                    $sheet->setAutoFilter($sheet->calculateWorksheetDataDimension());
                    foreach (range('A', $sheet->getHighestDataColumn()) as $column) $sheet->getColumnDimension($column)->setWidth(25);
                }
            }
            (new Xlsx($book))->save($path);
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        } finally {
            $book->disconnectWorksheets();
            Settings::setCache($previousCache);
            $files->deleteDirectory($cachePath);
        }

        return response()->download($path, $this->filename($data, 'xlsx'), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    public function pdf(array $data, ReservationReportService $service): BinaryFileResponse
    {
        $pdf = new class('L', 'mm', 'A4') extends \tFPDF {
            public array $report;

            public function Header()
            {
                $this->SetFont('DejaVu', '', 12);
                $this->SetTextColor(30, 58, 138);
                $this->Cell(0, 7, 'MCST Gymnasium Reservation and Utilization System', 0, 1);
                $this->SetFont('DejaVu', '', 10);
                $this->Cell(0, 6, $this->report['title'], 0, 1);
                $this->SetTextColor(71, 85, 105);
                $this->SetFont('DejaVu', '', 8);
                $this->Cell(0, 6, 'Period: '.$this->report['filters']['date_from'].' to '.$this->report['filters']['date_to'].' | Generated: '.$this->report['generatedAt']->format('Y-m-d H:i T'), 0, 1);
                $this->Ln(3);
            }

            public function Footer()
            {
                $this->SetY(-12);
                $this->SetFont('DejaVu', '', 8);
                $this->Cell(0, 6, 'MCST | Page '.$this->PageNo(), 0, 0, 'C');
            }
        };
        $pdf->report = $data;
        $pdf->SetTitle('MCST - '.$data['title']);
        $pdf->SetAuthor('MCST');
        $pdf->SetSubject($data['filters']['date_from'].' to '.$data['filters']['date_to']);
        $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();
        foreach ($data['metrics'] as $label => $value) $pdf->MultiCell(0, 6, $label.': '.$value);
        $pdf->Ln(2);
        $pdf->MultiCell(0, 5, $data['note']);
        $pdf->Ln(4);
        $widths = count($data['headers']) === 2 ? [210, 67] : [35, 64, 27, 16, 16, 80, 39];
        $header = function () use ($pdf, $widths, $data): void {
            $pdf->SetFillColor(238, 242, 255);
            foreach ($data['headers'] as $index => $label) $pdf->Cell($widths[$index], 8, $label, 1, 0, 'L', true);
            $pdf->Ln();
        };
        $header();
        foreach ($service->exportRows($data) as $values) {
            $wrapped = [];
            foreach ($values as $index => $value) {
                $lines = [''];
                foreach (mb_str_split(str_replace(["\r", "\n", "\t"], ' ', (string) $value)) as $character) {
                    $last = count($lines) - 1;
                    if ($pdf->GetStringWidth($lines[$last].$character) > $widths[$index] - 3) $lines[] = $character;
                    else $lines[$last] .= $character;
                }
                $wrapped[] = $lines;
            }
            $height = max(array_map('count', $wrapped)) * 5;
            if ($pdf->GetY() + $height > 190) {
                $pdf->AddPage();
                $header();
            }
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            foreach ($wrapped as $index => $lines) {
                $pdf->Rect($x, $y, $widths[$index], $height);
                $pdf->SetXY($x, $y);
                $pdf->MultiCell($widths[$index], 5, implode("\n", $lines));
                $x += $widths[$index];
            }
            $pdf->SetXY(10, $y + $height);
        }
        if ($data['total'] === 0) $pdf->MultiCell(0, 8, 'No reservations match the selected period.');
        $path = tempnam(sys_get_temp_dir(), 'mcst-pdf-');
        try {
            $pdf->Output('F', $path);
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }

        return response()->download($path, $this->filename($data, 'pdf'), ['Content-Type' => 'application/pdf'])->deleteFileAfterSend(true);
    }

    private function filename(array $data, string $extension): string
    {
        return 'mcst-'.$data['filters']['report_type'].'-'.$data['filters']['date_from'].'-to-'.$data['filters']['date_to'].'.'.$extension;
    }
}
