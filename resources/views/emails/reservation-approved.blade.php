<p>Hello {{ $reservation->contact_person }},</p>
<p>Your reservation <strong>{{ $reservation->reference_number }}</strong> for {{ $reservation->event_name }} has been approved.</p>
<p>{{ $reservation->reservation_date->format('F j, Y') }}, {{ substr($reservation->start_time, 0, 5) }} to {{ substr($reservation->end_time, 0, 5) }}.</p>
<p><a href="{{ route('reservation.track', ['reference' => $reservation->reference_number]) }}">Track your reservation</a></p>
