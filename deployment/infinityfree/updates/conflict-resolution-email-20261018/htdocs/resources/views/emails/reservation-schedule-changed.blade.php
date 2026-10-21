<p>Hello {{ $reservation->contact_person }},</p>
<p>Reservation: <strong>{{ $reservation->reference_number }}</strong> — {{ $reservation->event_name }}.</p>
<p>Previous schedule: {{ \Carbon\Carbon::parse($oldSchedule['reservation_date'])->format('F j, Y') }}, {{ substr($oldSchedule['start_time'], 0, 5) }}–{{ substr($oldSchedule['end_time'], 0, 5) }}.</p>
@if($outcome === 'waiting_list')
    <p>Your reservation has been moved to the waiting list because the administrator assigned a conflicting booking to this schedule. Your previous schedule is no longer confirmed. Please contact the administrator to arrange an available schedule.</p>
@else
    <p>Your reservation has been rescheduled by the administrator.</p>
    <p><strong>New schedule:</strong> {{ $reservation->reservation_date->format('F j, Y') }}, {{ substr($reservation->start_time, 0, 5) }}–{{ substr($reservation->end_time, 0, 5) }} ({{ config('app.timezone') }}).</p>
@endif
<p><strong>Reason:</strong> {{ $reason }}</p>
<p>Current status: {{ ucwords(str_replace('_', ' ', $reservation->status)) }}.</p>
<p><a href="{{ route('reservation.track', ['reference' => $reservation->reference_number]) }}">Track your reservation</a></p>
