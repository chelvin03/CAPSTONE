<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acknowledgement Receipt — {{ $booking['reference'] }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#eef2f7;color:#172033;font-family:Arial,sans-serif;font-size:14px;line-height:1.6}.toolbar{max-width:800px;margin:20px auto;display:flex;justify-content:space-between;gap:12px;padding:0 16px}.toolbar a,.toolbar button{padding:10px 16px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#1e40af;text-decoration:none;font:inherit;cursor:pointer}.toolbar button{background:#1d4ed8;color:#fff;border-color:#1d4ed8}.receipt{max-width:800px;margin:0 auto 30px;padding:40px;background:#fff;box-shadow:0 4px 24px #0f172a12}.letterhead{display:flex;justify-content:center;align-items:center;gap:18px;text-align:center;border-bottom:2px solid #1e40af;padding-bottom:22px}.letterhead img{width:75px;height:75px;object-fit:contain}.letterhead h1{font-size:19px;margin:0}.letterhead p{margin:2px 0;font-size:13px}h2{text-align:center;font-size:20px;margin:24px 0 18px;letter-spacing:1px}.reference{display:flex;justify-content:space-between;gap:15px;font-size:12px;margin-bottom:18px}.details{width:100%;border-collapse:collapse}.details th,.details td{border:1px solid #dbe1e9;padding:10px 12px;text-align:left;vertical-align:top}.details th{width:32%;font-weight:600;background:#f8fafc}.equipment{width:100%;border-collapse:collapse;margin-top:12px}.equipment th,.equipment td{padding:7px 10px;border:1px solid #dbe1e9;text-align:left}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:36px;margin-top:45px}.signature{border-top:1px solid #334155;padding-top:6px;text-align:center}.footnote{margin-top:30px;color:#64748b;font-size:11px;text-align:center}@page{size:A4;margin:18mm}@media print{body{background:#fff}.toolbar{display:none}.receipt{margin:0;padding:0;max-width:none;box-shadow:none}.signatures,.letterhead,tr{break-inside:avoid}a{color:inherit;text-decoration:none}}@media(max-width:600px){.receipt{padding:22px}.reference{flex-direction:column}.letterhead h1{font-size:16px}.signatures{gap:20px}}
    </style>
</head>
<body>
    <div class="toolbar"><a href="{{ $backUrl }}">Back to Reservation</a><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
    <main class="receipt">
        <header class="letterhead"><img src="{{ asset('images/admin-mcst-logo.jpg') }}" alt="MCST logo"><div><h1>Mandaluyong College of Science and Technology</h1><p>MCST Gymnasium</p><p>Reservation and Utilization System</p></div></header>
        <h2>ACKNOWLEDGEMENT RECEIPT</h2>
        <div class="reference"><span><strong>AR Number:</strong> {{ $receiptNumber }}</span><span><strong>Approval Date:</strong> {{ $booking['approved_at'] ? \Carbon\CarbonImmutable::parse($booking['approved_at'])->format('F j, Y') : 'Not recorded' }}</span></div>
        <p>This acknowledges the approved reservation of the gymnasium for the event and schedule below.</p>
        <table class="details"><tbody>
            @foreach([
                'Reservation Reference' => $booking['reference'],
                'Requestor / Contact Person' => $booking['requestor'],
                'Requestor Type' => ucwords(str_replace('_', ' ', $booking['requestor_type'])),
                'Event Name' => $booking['event'],
                'Facility' => $booking['facility'],
                'Reservation Date' => \Carbon\CarbonImmutable::parse($booking['date'])->format('F j, Y'),
                'Time' => \Carbon\CarbonImmutable::parse($booking['start'])->format('g:i A').' – '.\Carbon\CarbonImmutable::parse($booking['end'])->format('g:i A'),
                'Expected Participants' => number_format($booking['participants']),
                'Reservation Status' => 'Approved',
                'Approved By' => $booking['approved_by'],
            ] as $heading => $value)<tr><th>{{ $heading }}</th><td>{{ $value }}</td></tr>@endforeach
        </tbody></table>
        @php($approvedEquipment = collect($booking['equipment']))
        @if($approvedEquipment->isNotEmpty())
            <h3>Approved Equipment</h3><table class="equipment"><thead><tr><th>Equipment</th><th>Approved Quantity</th></tr></thead><tbody>@foreach($approvedEquipment as $item)<tr><td>{{ $item['name'] }}</td><td>{{ $item['quantity'] }}</td></tr>@endforeach</tbody></table>
        @endif
        <p>The requestor acknowledges the approved schedule and agrees to follow the gymnasium rules and care for the facilities and approved equipment.</p>
        @if($receipt->notes)<p><strong>Staff Notes:</strong> {{ $receipt->notes }}</p>@endif
        <div class="signatures"><div class="signature">{{ $preparedBy }}<br><strong>Prepared / Released By</strong></div><div class="signature">{{ $booking['requestor'] }}<br><strong>Requestor's Signature / Date</strong></div></div>
        <p class="footnote">{{ $receiptNumber }} · Keep this acknowledgement with your reservation records.</p>
    </main>
</body>
</html>
