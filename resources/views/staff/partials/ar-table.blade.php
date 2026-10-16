<div class="staff-event-scroll" tabindex="0" role="region" aria-label="Acknowledgement receipt tasks">
<table class="staff-event-table staff-ar-table">
    <thead><tr>@foreach(['Reference No.','Event Name','Reservation Date','Start Time','End Time','Expected Attendees','AR Status','Action'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
    <tbody>@forelse($rows as $row)
        @php
            $arStatus = $row->acknowledgementTask?->status ?? 'pending';
            $active = in_array($row->status, ['approved', 'completed'], true);
        @endphp
        <tr><td>{{ $row->reference_number }}</td><td>{{ $row->event_name }}@if($row->status !== 'approved')<small>{{ \App\Services\DashboardAnalyticsService::label($row->status) }} reservation</small>@endif</td><td>{{ $row->reservation_date->format('M j, Y') }}</td><td>{{ \Carbon\CarbonImmutable::parse($row->start_time)->format('h:i A') }}</td><td>{{ str_starts_with($row->end_time, '24:00') ? '12:00 AM (midnight)' : \Carbon\CarbonImmutable::parse($row->end_time)->format('h:i A') }}</td><td>{{ number_format($row->expected_attendees) }}</td>
        <td><span class="ar-badge ar-{{ $arStatus }}">{{ ['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed'][$arStatus] }}</span></td>
        <td><div class="staff-ar-actions">
            @if($active)
                @if($arStatus === 'pending')<form method="POST" action="{{ route('staff.reservations.receipt.start', $row) }}">@csrf<button type="submit" class="staff-ar-button">Create AR</button></form>
                @else<a href="{{ route('staff.reservations.receipt', $row) }}">{{ $row->acknowledgementReceipt ? 'View AR' : 'Prepare AR' }}</a>@endif
                @if($row->acknowledgementReceipt && $arStatus !== 'pending')<a href="{{ route('staff.reservations.receipt.print', $row) }}" target="_blank" rel="noopener">Print AR</a><a href="{{ route('staff.reservations.receipt.pdf', $row) }}">Download PDF</a>@endif
            @else<span class="reference-detail">Inactive booking; history retained</span>@endif
        </div></td></tr>
    @empty<tr><td colspan="8" class="staff-empty">No approved reservations require an acknowledgement receipt in this period.</td></tr>@endforelse</tbody>
</table>
</div>
