<div class="staff-event-scroll" tabindex="0" role="region" aria-label="{{ $caption }}">
    <table class="staff-event-table">
        <caption class="sr-only">{{ $caption }}</caption>
        <thead><tr><th scope="col">Event Name</th><th scope="col">Reservation Date</th><th scope="col">Start Time</th><th scope="col">End Time</th>@if($showPreparation)<th scope="col">Setup Time</th><th scope="col">Cleanup Time</th>@endif<th scope="col">Expected Attendees</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr><td><strong>{{ $row->event_name }}</strong><small>{{ $row->reference_number }}</small></td><td>{{ $row->reservation_date->format('M j, Y') }}</td><td>{{ substr($row->start_time, 0, 5) }}</td><td>{{ str_starts_with($row->end_time, '24:00') ? '24:00 (midnight)' : substr($row->end_time, 0, 5) }}</td>@if($showPreparation)<td>{{ $row->setup_time ? substr($row->setup_time, 0, 5) : 'Not specified' }}</td><td>{{ $row->cleanup_time ? substr($row->cleanup_time, 0, 5) : 'Not specified' }}</td>@endif<td>{{ number_format($row->expected_attendees) }}</td><td><x-operational-status :status="$row->status" /></td><td><a href="{{ route('staff.reservations.show', $row) }}" aria-label="View details for {{ $row->event_name }}">View Details</a></td></tr>
            @empty<tr><td colspan="{{ $showPreparation ? 9 : 7 }}" class="staff-empty">{{ $emptyMessage }}</td></tr>@endforelse
        </tbody>
    </table>
</div>
