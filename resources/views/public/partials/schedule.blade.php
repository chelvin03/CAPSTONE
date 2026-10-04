<div class="schedule-panel">
    <div class="schedule-heading"><span><i class="bi bi-calendar3" aria-hidden="true"></i> {{ $monthLabel ?? 'Upcoming occupied schedules · Next 30 days' }}</span><span class="schedule-legend"><span class="legend-dot"></span> Occupied schedules</span></div>
    @if($scheduleUnavailable)
        <div class="schedule-empty"><i class="bi bi-calendar-x" aria-hidden="true"></i><h3>Schedule temporarily unavailable</h3><p>Please try again later. Availability cannot be confirmed at this time.</p></div>
    @else
        @forelse($bookings as $booking)
            <div class="schedule-row"><div class="date-tile"><strong>{{ $booking->reservation_date->format('d') }}</strong><span>{{ $booking->reservation_date->format('M') }}</span></div><div><h3>{{ $booking->facility?->facility_name ?? 'Gymnasium' }}</h3><p>{{ $booking->reservation_date->format('D, F j, Y') }} · {{ \Carbon\Carbon::parse($booking->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($booking->end_time)->format('g:i A') }}</p></div><span class="booking-badge">Occupied</span></div>
        @empty
            <div class="schedule-empty"><i class="bi bi-calendar-check" aria-hidden="true"></i><h3>No occupied schedules to display</h3><p>This does not confirm that the displayed period is available.</p></div>
        @endforelse
        @foreach($blocks as $block)
            <div class="schedule-row"><i class="bi bi-calendar-minus block-icon" aria-hidden="true"></i><div><h3>{{ $block->facility?->facility_name ?? 'All facilities' }}</h3><p>{{ $block->starts_on->format('M j, Y') }} – {{ $block->ends_on->format('M j, Y') }} · {{ $block->start_time ? \Carbon\Carbon::parse($block->start_time)->format('g:i A').' – '.\Carbon\Carbon::parse($block->end_time)->format('g:i A') : 'All day' }}</p></div><span class="booking-badge blocked">Blocked</span></div>
        @endforeach
    @endif
    <p class="schedule-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Unlisted times may be available, subject to operating rules, blocked periods, pending requests, and conflict checks in the reservation form. All requests require review and approval.</p>
</div>
