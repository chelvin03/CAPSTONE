<p>Hello {{ $reservation->contact_person }},</p>
<p>Your MCST Gymnasium reservation <strong>{{ $reservation->reference_number }}</strong> for
    {{ $reservation->event_name }} on {{ $reservation->reservation_date->format('F j, Y') }}
    ({{ substr($reservation->start_time, 0, 5) }} – {{ substr($reservation->end_time, 0, 5) }}) has been cancelled.</p>
<p><strong>Cancellation reason:</strong> {{ $reservation->cancellation_reason }}</p>
<p>Please reschedule by submitting a new reservation request for an available date and time.</p>
<p><a href="{{ route('reservation.create') }}">Choose a new schedule</a></p>
<p><a href="{{ route('reservation.track', ['reference' => $reservation->reference_number]) }}">Track your cancelled reservation</a></p>
