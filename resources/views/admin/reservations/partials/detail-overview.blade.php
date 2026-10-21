<section class="detail-event detail-card">
    <header class="detail-card-heading"><h2><i class="bi bi-calendar3" aria-hidden="true"></i> Event Information</h2><a class="detail-small-button" href="{{ route('admin.reservations.edit', $reservation) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</a></header>
    <div class="detail-event-columns">
        <dl class="detail-fields">
            <div><dt>Event Name</dt><dd>{{ $reservation->event_name }}</dd></div>
            <div><dt>Reservation Type</dt><dd>{{ ucwords(str_replace('_', ' ', $reservation->reservation_type)) }}</dd></div>
            <div><dt>Event Type</dt><dd><span class="detail-event-type">{{ $reservation->event_type ?: $reservation->event_name }}</span></dd></div>
        </dl>
        <dl class="detail-fields">
            <div><dt>Expected Attendees</dt><dd>{{ number_format($reservation->expected_attendees) }}</dd></div>
            <div><dt>Gym Capacity</dt><dd>{{ number_format(\App\Support\GymCapacity::MAX_ATTENDEES) }}</dd></div>
            <div><dt>Capacity Usage</dt><dd><div class="detail-capacity-track" role="meter" aria-label="Capacity usage" aria-valuemin="0" aria-valuemax="{{ \App\Support\GymCapacity::MAX_ATTENDEES }}" aria-valuenow="{{ $reservation->expected_attendees }}"><span style="width:{{ min(100, max(0, $reservation->expected_attendees / \App\Support\GymCapacity::MAX_ATTENDEES * 100)) }}%"></span></div></dd></div>
        </dl>
    </div>
    <dl class="detail-fields detail-purpose"><div><dt>Purpose / Description</dt><dd>{{ $reservation->purpose }}</dd></div></dl>
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
<section class="detail-schedule detail-card">
    <header class="detail-card-heading"><h2><i class="bi bi-clock" aria-hidden="true"></i> Schedule Details</h2></header>
    @php
        $startMinutes = (int) substr($reservation->start_time, 0, 2) * 60 + (int) substr($reservation->start_time, 3, 2);
        $endMinutes = (int) substr($reservation->end_time, 0, 2) * 60 + (int) substr($reservation->end_time, 3, 2);
        $durationMinutes = max(0, ($endMinutes === 0 ? 1440 : $endMinutes) - $startMinutes);
    @endphp
    <dl class="detail-fields detail-schedule-fields">
        <div><dt>Facility</dt><dd>{{ $reservation->facility->facility_name ?? 'Not assigned' }}</dd></div>
        <div><dt>Date</dt><dd>{{ $reservation->reservation_date->format('F d, Y') }} <span class="detail-day">{{ $reservation->reservation_date->format('l') }}</span></dd></div>
        <div><dt>Start Time</dt><dd>{{ \Carbon\Carbon::parse($reservation->start_time)->format('g:i A') }}</dd></div>
        <div><dt>End Time</dt><dd>{{ in_array(substr($reservation->end_time, 0, 5), ['00:00', '24:00']) ? '12:00 AM (Midnight)' : \Carbon\Carbon::parse($reservation->end_time)->format('g:i A') }}</dd></div>
        <div><dt>Total Duration</dt><dd>{{ intdiv($durationMinutes, 60) }} hours {{ $durationMinutes % 60 }} minutes</dd></div>
    </dl>
</section>
