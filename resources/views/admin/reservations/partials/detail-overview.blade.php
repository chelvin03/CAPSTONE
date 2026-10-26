<section class="detail-event detail-card">
    <header class="detail-card-heading"><h2><i class="bi bi-calendar3" aria-hidden="true"></i> Event Details</h2><a class="detail-small-button" href="{{ route('admin.reservations.edit', $reservation) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</a></header>
    <div class="detail-event-columns">
        <dl class="detail-fields">
            <div><dt>Event Name</dt><dd>{{ $reservation->event_name }}</dd></div>
            <div><dt>Event Type</dt><dd><span class="detail-event-type">{{ $reservation->event_type ?: $reservation->event_name }}</span></dd></div>
            <div><dt>Reservation Type</dt><dd>{{ ucwords(str_replace('_', ' ', $reservation->reservation_type)) }}</dd></div>
            <div class="detail-purpose"><dt>Purpose / Description</dt><dd>{{ $reservation->purpose }}</dd></div>
        </dl>
        <dl class="detail-fields">
            <div><dt>Date</dt><dd>{{ $reservation->reservation_date->format('F d, Y') }} <span class="detail-day">{{ $reservation->reservation_date->format('l') }}</span></dd></div>
            <div><dt>Start Time</dt><dd>{{ $startLabel }}</dd></div>
            <div><dt>End Time</dt><dd>{{ $endLabel }}{{ $endLabel === '12:00 AM' ? ' (Midnight)' : '' }}</dd></div>
            <div><dt>Total Duration</dt><dd>{{ $durationLabel }}</dd></div>
            <div><dt>Facility</dt><dd>{{ $reservation->facility->facility_name ?? 'Not assigned' }}</dd></div>
            <div><dt>Expected Attendees</dt><dd>{{ number_format($reservation->expected_attendees) }} <small>({{ number_format($capacityUsage, 1) }}% of gym capacity: {{ number_format(\App\Support\GymCapacity::MAX_ATTENDEES) }})</small></dd></div>
        </dl>
    </div>
</section>
<section class="detail-requester detail-card">
    <header class="detail-card-heading"><h2><i class="bi bi-person-fill" aria-hidden="true"></i> Requester Information</h2></header>
    <dl class="detail-fields detail-requester-fields">
        <div class="detail-wide"><dt>Name</dt><dd>{{ $reservation->contact_person }}</dd></div>
        <div><dt>Contact Number</dt><dd>{{ $reservation->contact_number }}</dd></div>
        <div><dt>User Type</dt><dd>{{ ucwords(str_replace('_', ' ', $reservation->reservation_type)) }}</dd></div>
        <div><dt>Email Address</dt><dd>{{ $reservation->contact_email ?: 'N/A' }}</dd></div>
        <div><dt>Organization (if any)</dt><dd>{{ $reservation->organization_department ?: 'N/A' }}</dd></div>
    </dl>
</section>
