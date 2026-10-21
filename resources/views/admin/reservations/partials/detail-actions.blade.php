<section class="detail-action-card detail-card">
    <header class="detail-card-heading"><h2><i class="bi bi-gear-fill" aria-hidden="true"></i> Admin Actions</h2></header>
    <div class="detail-action-note"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><p>Review the reservation details and attached documents. Click the appropriate action based on the gymnasium’s policies and schedule availability.</p></div>
    @if(in_array($reservation->status, ['new', 'pending', 'pencil'], true))
    <form method="POST" action="{{ route('admin.reservations.review', $reservation) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="validate">
        <button class="detail-action-button detail-action-orange"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><strong>Validate Request</strong><small>Mark as validated after reviewing the details</small></span></button>
    </form>
    @endif
    @if(in_array($reservation->status, ['new', 'validated', 'waiting_list', 'pencil'], true))
    <form method="POST" action="{{ route('admin.reservations.approve', $reservation) }}" onsubmit="return confirm('Approve this reservation and confirm the schedule?')">@csrf @method('PATCH')
        <input type="hidden" name="admin_notes" value="{{ $reservation->admin_notes }}">
        <button class="detail-action-button detail-action-green"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><strong>Approve Reservation</strong><small>Confirm and assign the schedule</small></span></button>
    </form>
    @endif
    @if(in_array($reservation->status, ['new', 'pending', 'validated', 'pencil'], true))
    <form id="detail-waitlist-form" method="POST" action="{{ route('admin.reservations.review', $reservation) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="waitlist">
        <button class="detail-action-button detail-action-yellow" onclick="const reason = document.getElementById('detail-action-reason'); if (!reason.reportValidity()) return false; this.form.elements.reason.value = reason.value;"><i class="bi bi-clock-fill" aria-hidden="true"></i><span><strong>Move to Waiting List</strong><small>No available slot, add to waiting list</small></span></button>
    </form>
    @endif
    @if(!in_array($reservation->status, ['approved', 'rejected', 'cancelled', 'completed', 'no_show'], true))
    <form id="detail-reject-form" method="POST" action="{{ route('admin.reservations.reject', $reservation) }}" onsubmit="return confirm('Reject this reservation with the entered reason?')">@csrf @method('PATCH')
        <button class="detail-action-button detail-action-red"><i class="bi bi-x-circle-fill" aria-hidden="true"></i><span><strong>Reject Reservation</strong><small>Decline the request with a reason</small></span></button>
    </form>
    <label class="detail-reason-label" for="detail-action-reason">Reason (for rejection or waiting list)</label>
    <textarea id="detail-action-reason" class="detail-reason" name="rejection_reason" form="detail-reject-form" required rows="4" placeholder="Enter the reason here...">{{ old('rejection_reason', old('reason')) }}</textarea>
    @if(in_array($reservation->status, ['new', 'pending', 'validated', 'pencil'], true))<input form="detail-waitlist-form" type="hidden" name="reason" value="{{ old('reason', old('rejection_reason')) }}">@endif
    <p class="detail-delivery-note"><i class="bi bi-envelope-check" aria-hidden="true"></i> An approval email is sent when the requestor has an email address.</p>
    @else
    <p class="detail-readonly-status">Current status: <strong>{{ ucwords(str_replace('_', ' ', $reservation->status)) }}</strong></p>
    @endif
</section>
