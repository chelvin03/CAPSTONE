<p>Hello {{ $followUp['reservation']->contact_person }},</p>
<p>Reservation: <strong>{{ $followUp['reservation']->reference_number }}</strong></p>
<p>{{ $followUp['message'] }}</p>
<p>Schedule: {{ $followUp['reservation']->reservation_date->toDateString() }}, {{ substr($followUp['reservation']->start_time, 0, 5) }} to {{ substr($followUp['reservation']->end_time, 0, 5) === '24:00' ? '12:00 midnight' : substr($followUp['reservation']->end_time, 0, 5) }} ({{ config('app.timezone') }}).</p>
<p><a href="{{ $followUp['link'] }}">{{ $followUp['kind'] === 'proposal' ? 'Review the offer and Accept or Decline' : 'Track your reservation' }}</a></p>
<p>This reminder does not approve, cancel or change your booking. If you need help, contact the gym administrator.</p>
