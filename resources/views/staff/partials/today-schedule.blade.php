<div class="staff-event-scroll" tabindex="0" role="region" aria-label="Today's Gymnasium Schedule">
    <table class="staff-event-table staff-today-table">
        <thead><tr><th scope="col">Event Name</th><th scope="col">Start Time</th><th scope="col">End Time</th><th scope="col">Expected Attendees</th><th scope="col">Status</th></tr></thead>
        <tbody>@forelse($rows as $row)
            <tr><td><a href="{{ route('staff.reservations.show', $row) }}" aria-label="View Details for {{ $row->event_name }}">{{ $row->event_name }}</a></td><td>{{ \Carbon\CarbonImmutable::parse($row->start_time)->format('h:i A') }}</td><td>{{ str_starts_with($row->end_time, '24:00') ? '12:00 AM (midnight)' : \Carbon\CarbonImmutable::parse($row->end_time)->format('h:i A') }}</td><td>{{ number_format($row->expected_attendees) }}</td><td><x-operational-status :status="$row->status" /></td></tr>
        @empty<tr><td colspan="5" class="staff-empty">No gymnasium events are scheduled for today.</td></tr>@endforelse</tbody>
    </table>
</div>
