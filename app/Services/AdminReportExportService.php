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
            ], null, 'A1', true);
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
        $pdf = new ReportPdf('L', 'mm', 'A4');
        $pdf->report = $data;
        $pdf->SetTitle('MCST - '.$data['title']);
        $pdf->SetAuthor('MCST');
        $pdf->SetSubject($data['filters']['date_from'].' to '.$data['filters']['date_to']);
        $pdf->registerReportFont();
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();
        foreach ($data['metrics'] as $label => $value) $pdf->MultiCell(0, 6, $label.': '.$value);
        $pdf->Ln(2);
        $pdf->MultiCell(0, 5, $data['note']);
        $pdf->Ln(4);
        // Landscape A4 provides 277 mm between the default 10 mm margins.
        $widths = match (count($data['headers'])) {
            2 => [210, 67],
            12 => [25, 32, 25, 28, 26, 14, 14, 32, 29, 30, 25, 40],
            default => [32, 51, 27, 15, 15, 47, 32, 28, 30],
        };
        $scale = 277 / array_sum($widths);
        $widths = array_map(fn ($width) => $width * $scale, $widths);
        $header = function () use ($pdf, $widths, $data): void {
            $pdf->SetFillColor(238, 242, 255);
            $height = count($data['headers']) === 12 ? 16 : 8;
            if ($pdf->GetY() + $height > 190) $pdf->AddPage();
            $x = 10;
            $y = $pdf->GetY();
            foreach ($data['headers'] as $index => $label) {
                $pdf->Rect($x, $y, $widths[$index], $height, 'DF');
                $pdf->SetXY($x, $y);
                $pdf->MultiCell($widths[$index], 4, $label);
                $x += $widths[$index];
            }
            $pdf->SetXY(10, $y + $height);
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
            $remaining = max(array_map('count', $wrapped));
            $offset = 0;
            while ($remaining > 0) {
                $fit = (int) floor((190 - $pdf->GetY()) / 5);
                if ($fit < 1) {
                    $pdf->AddPage();
                    $header();
                    $fit = (int) floor((190 - $pdf->GetY()) / 5);
                }
                $take = min($remaining, $fit);
                $height = $take * 5;
                $x = 10;
                $y = $pdf->GetY();
                foreach ($wrapped as $index => $lines) {
                    $pdf->Rect($x, $y, $widths[$index], $height);
                    $pdf->SetXY($x, $y);
                    $pdf->MultiCell($widths[$index], 5, implode("\n", array_slice($lines, $offset, $take)));
                    $x += $widths[$index];
                }
                $pdf->SetXY(10, $y + $height);
                $offset += $take;
                $remaining -= $take;
            }
        }
        if ($data['total'] === 0) $pdf->MultiCell(0, 8, 'No reservations match the selected period.');
        $path = tempnam(sys_get_temp_dir(), 'mcst-pdf-');
        if ($path === false) throw new \RuntimeException('The PDF temporary folder is not writable.');
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
