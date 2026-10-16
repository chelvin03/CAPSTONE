<?php

namespace App\Services;

use App\Models\AcknowledgementReceipt;
use Carbon\CarbonImmutable;

class AcknowledgementPdfService
{
    public function render(AcknowledgementReceipt $receipt): string
    {
        $booking = $receipt->snapshot['booking'];
        $pdf = new \tFPDF('P', 'mm', 'A4');
        $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $pdf->SetMargins(18, 18, 18);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetTitle('MCST Acknowledgement Receipt');
        $pdf->AddPage();
        $pdf->Image(public_path('images/admin-mcst-logo.jpg'), 18, 16, 22);
        $pdf->SetFont('DejaVu', '', 13);
        $pdf->SetTextColor(30, 58, 138);
        $pdf->MultiCell(0, 7, 'Mandaluyong College of Science and Technology', 0, 'R');
        $pdf->MultiCell(0, 7, 'MCST Gymnasium', 0, 'R');
        $pdf->Ln(9);
        $pdf->MultiCell(0, 8, 'ACKNOWLEDGEMENT RECEIPT', 0, 'C');
        $pdf->SetTextColor(23, 32, 51);
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Ln(5);
        $date = fn ($value) => $value ? CarbonImmutable::parse($value)->format('F j, Y') : 'Not recorded';
        foreach ([
            'AR Number' => $receipt->receipt_number,
            'Date Issued' => $receipt->prepared_at->format('F j, Y'),
            'Reservation Reference' => $booking['reference'],
            'Requestor / Contact Person' => $booking['requestor'],
            'Requestor Type' => DashboardAnalyticsService::label($booking['requestor_type']),
            'Event Name' => $booking['event'],
            'Event Type' => DashboardAnalyticsService::label($booking['event_type'] ?? null),
            'Facility' => $booking['facility'],
            'Reservation Date' => $date($booking['date']),
            'Time' => $booking['start'].' – '.$booking['end'].($booking['end'] === '24:00' ? ' (midnight)' : ''),
            'Expected Attendees' => number_format($booking['participants']),
            'Approved By' => $booking['approved_by'],
            'Approval Date' => $date($booking['approved_at']),
        ] as $label => $value) {
            $pdf->MultiCell(0, 7, $label.': '.$value, 'B');
        }
        if ($booking['equipment']) {
            $pdf->Ln(4);
            $pdf->MultiCell(0, 7, 'Approved Equipment');
            foreach ($booking['equipment'] as $item) $pdf->MultiCell(0, 6, $item['name'].': '.$item['quantity']);
        }
        $pdf->Ln(6);
        $pdf->MultiCell(0, 6, 'This acknowledges the approved reservation of the gymnasium for the event and schedule above. The requestor agrees to follow gymnasium rules and care for the facilities and approved equipment.');
        if ($receipt->notes) $pdf->MultiCell(0, 6, 'Staff Notes: '.$receipt->notes);
        $pdf->Ln(15);
        $pdf->MultiCell(0, 6, 'Prepared By: '.$receipt->snapshot['prepared_name']);
        $pdf->MultiCell(0, 8, 'Staff Signature: __________________________  Date: ______________');
        $pdf->Ln(8);
        $pdf->MultiCell(0, 6, 'Requestor: '.$booking['requestor']);
        $pdf->MultiCell(0, 8, 'Signature: ______________________________  Date: ______________');
        return $pdf->Output('S');
    }
}
