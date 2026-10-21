<?php

namespace App\Services;

// A manual library upload may precede the host's Composer class-map update.
if (! class_exists(\tFPDF::class)) {
    $library = dirname(__DIR__, 2).'/vendor/setasign/tfpdf/tfpdf.php';
    if (! is_readable($library)) {
        throw new \RuntimeException('The PDF library is missing or unreadable.');
    }
    require_once $library;
}
if (! class_exists(\TTFontFile::class)) {
    $fontLibrary = dirname(__DIR__, 2).'/vendor/setasign/tfpdf/font/unifont/ttfonts.php';
    if (! is_readable($fontLibrary)) {
        throw new \RuntimeException('The PDF font parser is missing or unreadable.');
    }
    require_once $fontLibrary;
}

class ReportPdf extends \tFPDF
{
    public array $report;

    public function registerReportFont(): void
    {
        $font = $this->fontpath.'unifont/DejaVuSans.ttf';
        if (! is_readable($font)) throw new \RuntimeException('The report Unicode font is missing or unreadable.');
        $this->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        // tFPDF metrics cache contains the absolute path from the machine that
        // generated it. Resolve it again after uploading vendor to another host.
        $this->fonts['dejavu']['ttffile'] = $font;
        $this->FontFiles['dejavu']['ttffile'] = $font;
    }

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
}
