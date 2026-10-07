@php
    $labels = ['new' => 'New - Submitted', 'validated' => 'Validated', 'approved' => 'Approved', 'waiting_list' => 'Waiting List', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'completed' => 'Completed'];
    $messages = [
        'new' => 'Your reservation request has been submitted and is waiting for administrator review.',
        'validated' => 'Your reservation request has been reviewed and validated by the administrator.',
        'approved' => 'Your reservation has been approved. Your gymnasium schedule is confirmed.',
        'waiting_list' => 'The requested schedule is currently occupied. Your reservation has been placed on the waiting list.',
        'rejected' => 'Your reservation request has been rejected by the administrator.',
        'cancelled' => 'Your reservation has been cancelled.',
        'completed' => 'Your reservation has been completed.',
    ];
    $stages = ['new' => 'Submitted', 'validated' => 'Validated', 'approved' => 'Approved', 'completed' => 'Completed'];
    $rank = array_search($reservation->status, array_keys($stages), true);
    $reached = $reservation->statusHistories->pluck('new_status')->all();
@endphp
<section class="reservation-tracking" aria-labelledby="tracking-heading">
    <div class="tracking-reference" x-data="{ copied: false, copyError: false }">
        <div><span>Reservation Reference Number</span><strong>{{ $reservation->reference_number }}</strong></div>
        <button type="button" class="tracking-button" @click="try { await navigator.clipboard.writeText(@js($reservation->reference_number)); copied = true; copyError = false; } catch (error) { copyError = true; }" x-text="copied ? 'Copied!' : 'Copy Reference Number'">Copy Reference Number</button>
        <span role="status" x-show="copyError">Please select and copy the reference number above.</span>
    </div>
    <div class="tracking-heading"><h2 id="tracking-heading">Reservation Tracking</h2><span class="tracking-status">{{ $labels[$reservation->status] ?? ucwords(str_replace('_', ' ', $reservation->status)) }}</span></div>
    <ol class="tracking-timeline" aria-label="Reservation progress">
        @foreach($stages as $status => $label)
            @php($done = $status === 'new' || ($rank !== false && $loop->index <= $rank) || in_array($status, $reached, true))
            <li @class(['tracking-stage', 'is-reached' => $done]) @if($reservation->status === $status) aria-current="step" @endif><span aria-hidden="true">{{ $done ? '✓' : '○' }}</span><strong>{{ $label }}</strong><span class="sr-only">{{ $done ? 'Reached' : 'Upcoming' }}</span></li>
        @endforeach
    </ol>
    <p class="tracking-message" role="status">{{ $messages[$reservation->status] ?? 'Your reservation is being processed.' }}</p>
    <h3>Reservation Details</h3>
    <dl class="tracking-details">
        @foreach([
            'Requestor Name' => $reservation->contact_person,
            'Event Name' => $reservation->event_name,
            'Event Type' => $reservation->event_type ?? 'Not specified',
            'Reservation Date' => $reservation->reservation_date->format('F d, Y'),
            'Start Time' => \Carbon\Carbon::parse($reservation->start_time)->format('g:i A'),
            'End Time' => \Carbon\Carbon::parse($reservation->end_time)->format('g:i A'),
            'Number of Participants' => $reservation->expected_attendees,
            'Facility/Gymnasium' => $reservation->facility?->facility_name ?? 'Unassigned',
            'Date Submitted' => $reservation->created_at->format('F d, Y g:i A'),
            'Current Status' => $labels[$reservation->status] ?? ucfirst($reservation->status),
        ] as $label => $value)
        <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
        @endforeach
    </dl>
    @include('public.reservations.partials.equipment-updates')
    @if($reservation->statusHistories->isNotEmpty())
    <h3>Status History</h3><ol class="tracking-history">
        @foreach($reservation->statusHistories->sortByDesc('created_at') as $history)
        <li><strong>{{ $labels[$history->new_status] ?? ucwords(str_replace('_', ' ', $history->new_status)) }}</strong><time>{{ $history->created_at->format('M d, Y g:i A') }}</time></li>
        @endforeach
    </ol>
    @endif
</section>
