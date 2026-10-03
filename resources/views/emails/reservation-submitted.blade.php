<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Reservation request confirmation</title></head>
<body style="font-family:Arial,sans-serif;color:#0f172a;background:#f1f5f9;padding:24px;">
<div style="max-width:560px;margin:auto;background:white;padding:28px;border-radius:12px;">
    <h1 style="font-size:22px;color:#136be7;">MCST Gymnasium Reservation System</h1>
    <h2>Reservation request received</h2>
    <p>Hello {{ $reservation->contact_person }},</p>
    <p style="white-space:pre-line;">{{ $intro }}</p>
    <p><strong>Reference number:</strong> {{ $reservation->reference_number }}</p>
    <p><strong>Event:</strong> {{ $reservation->event_name }}</p>
    <p><strong>Facility:</strong> {{ $reservation->facility?->facility_name }}</p>
    <p><strong>Date:</strong> {{ $reservation->reservation_date->format('F d, Y') }}</p>
    <p><strong>Time:</strong> {{ \Carbon\Carbon::parse($reservation->start_time)->format('g:i A') }} &ndash; {{ \Carbon\Carbon::parse($reservation->end_time)->format('g:i A') }}</p>
    <p><strong>Status: Awaiting administrator review.</strong> This email confirms receipt of your request. Your booking still requires administrator approval.</p>
    <p><a href="{{ $trackingUrl }}" style="display:inline-block;background:#136be7;color:white;padding:12px 20px;border-radius:6px;">Track Reservation</a></p>
    <p>Save your reference number. You can check the latest status and status history using the tracking link.</p>
    <p style="word-break:break-all;">{{ $trackingUrl }}</p>
</div>
</body>
</html>
