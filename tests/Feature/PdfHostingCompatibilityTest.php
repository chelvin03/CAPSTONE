<?php

use App\Services\ReportPdf;
use Illuminate\Support\Facades\File;

test('uploaded font metrics with a different machines absolute path reproduce the PDF error and are repaired', function () {
    $directory = storage_path('framework/testing/pdf-font-'.uniqid());
    File::ensureDirectoryExists($directory.'/unifont');
    try {
        copy(base_path('vendor/setasign/tfpdf/font/unifont/DejaVuSans.ttf'), $directory.'/unifont/DejaVuSans.ttf');
        $legacy = new class extends \tFPDF {
            public function directory(string $directory): void { $this->fontpath = $directory.'/'; }
        };
        $legacy->directory($directory);
        $legacy->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $metrics = $directory.'/unifont/dejavusans.mtx.php';
        $source = file_get_contents($metrics);
        $source = preg_replace('/\$ttffile=.*;/', '\$ttffile=\'/missing-upload-machine/DejaVuSans.ttf\';', $source);
        file_put_contents($metrics, $source);

        $broken = new class extends \tFPDF {
            public function directory(string $directory): void { $this->fontpath = $directory.'/'; }
        };
        $broken->directory($directory);
        $broken->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $broken->AddPage();
        $broken->SetFont('DejaVu', '', 10);
        $broken->Cell(0, 6, 'Hosted report');
        expect(fn () => $broken->Output('S'))->toThrow(\ErrorException::class);

        $fixed = new class extends ReportPdf {
            public function directory(string $directory): void { $this->fontpath = $directory.'/'; }
        };
        $fixed->directory($directory);
        $fixed->report = ['title' => 'Hosted report', 'filters' => ['date_from' => '2026-10-01', 'date_to' => '2026-10-31'], 'generatedAt' => now()];
        $fixed->registerReportFont();
        $fixed->AddPage();
        $fixed->SetFont('DejaVu', '', 10);
        $fixed->Cell(0, 6, 'Hosted report with Unicode: Peña');
        expect($fixed->Output('S'))->toStartWith('%PDF-')->toContain('%%EOF');
    } finally {
        File::deleteDirectory($directory);
    }
});
