@php
    $labels = ['new' => 'Pending', 'validated' => 'Validated', 'approved' => 'Approved', 'waiting_list' => 'Waiting List', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'completed' => 'Completed'];
    $messages = [
        'new' => 'Your reservation request has been submitted and is waiting for administrator review.',
        'validated' => 'Your reservation request has been reviewed and validated by the administrator.',
        'approved' => 'Your reservation has been approved. Your gymnasium schedule is confirmed.',
        'waiting_list' => 'The requested schedule is currently occupied. Your reservation has been placed on the waiting list.',
        'rejected' => 'Your reservation request has been rejected by the administrator.',
        'cancelled' => 'Your reservation has been cancelled.',
        'completed' => 'Your reservation has been completed.',
    ];
    $statusColor = match ($reservation->status) {
        'approved', 'completed' => 'approved',
        'cancelled', 'rejected' => 'declined',
        default => 'pending',
    };
@endphp
<section class="reservation-tracking" aria-labelledby="tracking-heading">
    <div class="tracking-reference" x-data="{ copied: false, copyError: false }">
        <div><span>Reservation Reference Number</span><strong>{{ $reservation->reference_number }}</strong></div>
        <button type="button" class="tracking-button" @click="try { await navigator.clipboard.writeText(@js($reservation->reference_number)); copied = true; copyError = false; } catch (error) { copyError = true; }" x-text="copied ? 'Copied!' : 'Copy Reference Number'">Copy Reference Number</button>
        <span role="status" x-show="copyError">Please select and copy the reference number above.</span>
    </div>
    <div class="tracking-heading"><h2 id="tracking-heading">Reservation Tracking</h2><span class="tracking-status tracking-status-{{ $statusColor }}">{{ $labels[$reservation->status] ?? ucwords(str_replace('_', ' ', $reservation->status)) }}</span></div>
    <p class="tracking-message" role="status">{{ $messages[$reservation->status] ?? 'Your reservation is being processed.' }}</p>
    @if($reservation->status === 'cancelled')
        <p class="tracking-message"><strong>Cancellation reason:</strong> {{ $reservation->cancellation_reason ?: 'Please contact the administrator for details.' }}</p>
        <p>Please reschedule by submitting a new reservation request for an available date and time.</p>
        <a href="{{ route('reservation.create') }}">Choose a new schedule</a>
    @endif
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
