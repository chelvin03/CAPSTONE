@if(!in_array($reservation->status, ['cancelled', 'rejected', 'completed', 'no_show'], true))
@php
    $scheduleMinutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    $originalMinutes = ($scheduleMinutes($reservation->end_time) ?: 1440) - $scheduleMinutes($reservation->start_time);
@endphp
                <form method="POST" action="{{route('admin.reservations.reschedule',$reservation)}}" class="detail-scheduling-grid"
                    x-data="{
                        ...scheduleImpactReview(@js(route('admin.reservations.reschedule-preview', $reservation))),
                        duration: {{ $originalMinutes }}, closing: {{ $scheduleMinutes(config('gym.reservation.closing_time', '24:00')) }},
                        start: @js(old('start_time', substr($reservation->start_time, 0, 5))),
                        end: @js(old('end_time', substr($reservation->end_time, 0, 5))),
                        adjustDuration: @js((bool) old('adjust_duration', false)),
                        forceMode: @js((bool) old('force_assign', false)),
                        get sendProposal() { return !this.forceMode; },
                        selectedScheduleDate: @js(old('reservation_date', $reservation->reservation_date->format('Y-m-d'))),
                        originalScheduleDate: @js($reservation->reservation_date->format('Y-m-d')),
                        originalStart: @js(substr($reservation->start_time, 0, 5)),
                        originalEnd: @js(substr($reservation->end_time, 0, 5) === '00:00' ? '24:00' : substr($reservation->end_time, 0, 5)),
                        waitingOffer: @js($reservation->status === 'waiting_list'),
                        get durationError() {
                            if (!this.start || !this.end) return 'Choose a start and end time before proceeding.';
                            const minutes = time => time.split(':').reduce((hours, part) => hours * 60 + Number(part), 0);
                            const length = minutes(this.end) - minutes(this.start);
                            if (length &lt;= 0) return 'End time must be after start time.';
                            if (length &lt; 120) return 'Cannot proceed: reservations must be at least 2 hours. The selected duration is ' + length + ' minutes.';
                            if (minutes(this.end) &gt; this.closing) return 'Cannot proceed: the reservation must end before closing.';
                            if (!this.waitingOffer && this.selectedScheduleDate === this.originalScheduleDate && this.start === this.originalStart && this.end === this.originalEnd) return 'No schedule changes detected. Choose a different date or time.';
                            return '';
                        },
                        updateEnd() {
                            if (this.adjustDuration || !this.start) return;
                            const [h, m] = this.start.split(':').map(Number);
                            const finish = h * 60 + m + this.duration;
                            if (this.duration &lt; 120 || finish &gt; this.closing || finish &gt; 1440) {
                                this.end = ''; return;
                            }
                            this.end = String(Math.floor(finish / 60)).padStart(2, '0') + ':' + String(finish % 60).padStart(2, '0');
                        }
                    }" x-init="updateEnd()" @submit.prevent="reviewImpact($event)">@csrf @method('PATCH')

    <section id="conflict-resolution" class="detail-card detail-reschedule-card">
        <header class="detail-card-heading"><h2><i class="bi bi-calendar-range" aria-hidden="true"></i> Conflict Resolution / Reschedule</h2></header>
        <p class="detail-scheduling-help">Move this reservation to an available schedule if there is a conflict or the original schedule is not feasible.</p>
        <details class="detail-proposal-options"><summary class="detail-proposal-button"><i class="bi bi-calendar3" aria-hidden="true"></i> Propose New Schedule</summary>
        <p class="detail-schedule-error" role="alert" aria-live="polite" x-show="durationError" x-text="durationError"></p>
        <h3 class="detail-reschedule-title">Reschedule Options</h3>
        <div class="detail-reschedule-inputs">
            <label>New Date<input type="date" name="reservation_date" x-model="selectedScheduleDate" required value="{{ old('reservation_date', $reservation->reservation_date->format('Y-m-d')) }}"></label>
            <label>Start Time<input type="time" step="1800" name="start_time" x-model="start" @input="updateEnd()" required value="{{ old('start_time', substr($reservation->start_time, 0, 5)) }}"></label>
                    <label>End Time<select name="end_time" x-model="end" :disabled="!adjustDuration" required class="block w-full rounded-lg border-slate-300">
                        <option value="">Choose end time</option>
                        @foreach(range(1, 48) as $halfHour)
                            @php($endOption = sprintf('%02d:%02d', intdiv($halfHour, 2), $halfHour % 2 * 30))
                            <option value="{{ $endOption }}" @selected(old('end_time', substr($reservation->end_time, 0, 5)) === $endOption)>{{ $endOption === '24:00' ? '12:00 midnight (end of selected date)' : \Carbon\Carbon::createFromTime(intdiv($halfHour, 2), $halfHour % 2 * 30)->format('g:i A') }}</option>
                        @endforeach
                    </select></label>

            <button type="submit" class="detail-check-availability" :disabled="impactBusy || !!durationError" x-text="impactBusy ? 'Checking...' : 'Check Availability'">Check Availability</button>
        </div>
        <label class="detail-duration-toggle"><input type="checkbox" name="adjust_duration" value="1" x-model="adjustDuration" @change="updateEnd()"> Adjust original duration ({{ intdiv(max(0, $originalMinutes), 60) }} hours {{ max(0, $originalMinutes) % 60 }} minutes)</label>
        <input type="hidden" name="send_proposal" :value="sendProposal ? 1 : 0" value="1">
        <p class="detail-scheduling-help" x-show="sendProposal">Check availability, then review and confirm the proposal. The requestor must accept the new schedule; the slot is not held until acceptance.</p>
        @if($reservation->status === 'waiting_list')
        <label class="detail-duration-toggle"><input type="checkbox" name="requirements_reviewed" value="1" :required="sendProposal"> I reviewed eligibility and required documents before offering this slot.</label>
        @endif
                @php($scheduleProposals = \App\Models\ScheduleProposal::where('reservation_id', $reservation->id)->latest()->limit(5)->get())
                @if($scheduleProposals->isNotEmpty())
                    <div class="mt-5 border-t border-blue-200 pt-4">
                        <h3 class="font-bold">Replacement proposals</h3>
                        @foreach($scheduleProposals as $scheduleProposal)
                            <p class="my-3 text-sm">{{ $scheduleProposal->target['reservation_date'] }} {{ $scheduleProposal->target['start_time'] }}–{{ $scheduleProposal->target['end_time'] }}:
                                <strong>{{ $scheduleProposal->status === 'pending' ? ($scheduleProposal->expires_at->isPast() ? 'Expired' : ($scheduleProposal->original !== app(\App\Services\ScheduleProposalService::class)->snapshot($reservation) ? 'No longer applicable' : 'Awaiting requestor response')) : ucfirst($scheduleProposal->status) }}</strong>.
                                Email: {{ $scheduleProposal->mail_status }}. Reply deadline: {{ $scheduleProposal->expires_at->format('M j, Y g:i A') }}.
                                @if($scheduleProposal->mail_status === 'failed')<span class="text-red-700">Email failed. Check mail settings and send a new proposal.</span>@endif
                            </p>
                        @endforeach
                    </div>
                @endif

        </details>
    </section>
    <section class="detail-card detail-force-card">
        <header class="detail-card-heading"><h2><i class="bi bi-person-fill-gear" aria-hidden="true"></i> Force Assign (Direct Admin Action)</h2></header>
        <label class="detail-force-toggle"><input type="checkbox" name="force_assign" value="1" x-model="forceMode"> Force assign this reservation</label>
        <p class="detail-scheduling-help">Apply the selected schedule directly. Conflicting bookings use the resolution chosen below. Capacity, equipment, closures and completed bookings are still checked.</p>
        <p class="detail-force-warning"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span>Force assignment can affect other bookings. Review affected requestors before confirming.</span></p>
        <label class="detail-resolution-select" x-show="forceMode">Conflict resolution<select name="conflict_resolution" :disabled="sendProposal"><option value="cancelled">Cancel affected bookings and email requestors</option><option value="waiting_list">Move affected bookings to waiting list and email requestors</option></select></label>
        <label class="detail-schedule-reason" for="schedule-change-reason" x-text="forceMode ? 'Reason for force assignment (required)' : 'Reason for schedule change (required)'">Reason for schedule change (required)</label>
        <textarea id="schedule-change-reason" name="reason" required rows="2" placeholder="Enter the reason...">{{ old('reason') }}</textarea>
        <button type="submit" class="detail-force-submit" :disabled="!forceMode || impactBusy || !!durationError" x-text="impactBusy ? 'Reviewing...' : 'Force Assign Reservation'">Force Assign Reservation</button>
    </section>
    @include('admin.schedule.partials.impact-review')
</form>
@endif
