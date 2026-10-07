<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Equipment Request Update</title></head>
<body style="font-family: Poppins, Arial, sans-serif; color: #1E3A8A; background: #F8FAFC; padding: 24px;">
    <h1>Equipment Request Update</h1>
    <p>Reservation Reference: <strong>{{ $reservation->reference_number }}</strong></p>
    <p>The Gym Administrator sent you an update regarding your equipment request:</p>
    <p style="white-space: pre-line;">{{ $details['message'] }}</p>
    @if(isset($details['equipment_name']))
        <p>Equipment: {{ $details['equipment_name'] }}<br>Requested: {{ $details['requested'] }}<br>Available when sent: {{ $details['available'] }}<br>Approved/Offered: {{ $details['quantity'] }}</p>
    @endif
    <p><a href="{{ $trackingUrl }}" style="color: #2563EB;">View Equipment Updates / Respond</a></p>
    <p>This secure link expires in seven days. Equipment offers remain subject to availability and final approval by the Gym Administrator.</p>
</body></html>
