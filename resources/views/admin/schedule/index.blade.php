@extends('layouts.app')
@section('title', 'Gymnasium Schedule')
@section('content')
<link rel="stylesheet" href="{{ asset('css/admin-schedule.css') }}?v={{ filemtime(public_path('css/admin-schedule.css')) }}">
<link rel="stylesheet" href="{{ asset('css/schedule-impact.css') }}?v={{ filemtime(public_path('css/schedule-impact.css')) }}"><script src="{{ asset('js-schedule-impact.js') }}?v={{ filemtime(public_path('js-schedule-impact.js')) }}"></script>
<div class="admin-schedule page-shell" x-data="adminSchedule(@js($viewDays), @js($selectedDate), @js($reservations->pluck('reservation_date')->map(fn ($date) => $date->toDateString())->values()), @js($scheduleView), @js($hours))">
    <div class="page-header"><div><p class="schedule-eyebrow">ADMIN · SCHEDULE MANAGEMENT</p><h1 class="page-title">Gymnasium Schedule</h1><p class="page-description">Monitor approved reservations, pending requests, and available gymnasium time slots.</p></div><a class="btn-secondary" href="{{ route('admin.reservations.index') }}"><i class="bi bi-list-check" aria-hidden="true"></i> Manage Reservations</a></div>
    @if($errors->any())<div class="alert-error" role="alert"><div><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
    <form class="schedule-filters card" method="GET" action="{{ route('admin.schedule.index') }}" @submit="loading = true">
        <div><label for="schedule-month" class="field-label">Month and Year</label><input class="field" id="schedule-month" type="month" name="month" value="{{ $month }}" required></div>
        <div><label for="schedule-status" class="field-label">Reservation Status</label><select class="field" id="schedule-status" name="status"><option value="">All statuses</option>@foreach([...config('gym.statuses'), 'pending', 'pencil'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}{{ $status === 'pending' ? ' (legacy)' : '' }}</option>@endforeach</select></div>
        <div><label for="schedule-event" class="field-label">Event Type</label><select class="field" id="schedule-event" name="event_type"><option value="">All event types</option>@foreach($eventTypes as $type)<option value="{{ $type }}" @selected(($filters['event_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
        <button type="submit" class="btn-primary">Apply Filters</button><a class="btn-secondary" href="{{ route('admin.schedule.index') }}">Reset</a>
    </form>
    <p class="schedule-context"><i class="bi bi-info-circle" aria-hidden="true"></i> Status and event filters apply to the details table. Availability always includes all active requests for the gym. Pending requests and waiting-list entries are not confirmed bookings.</p>
    <div x-show="loading" x-cloak class="schedule-loading" role="status"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Loading schedule…</div>
    @include('admin.schedule.partials.views')
    <details class="schedule-technical-details"><summary>Detailed intervals and conflict review</summary>
    <div class="schedule-workspace">
        <section class="card calendar-panel" aria-labelledby="calendar-heading">
            <div class="schedule-panel-heading"><div><h2 id="calendar-heading">{{ $monthLabel }}</h2><p>{{ $facilities->count() ? (isset($filters['facility_id']) ? $facilities->firstWhere('id', (int)$filters['facility_id'])?->facility_name : 'MCST Gymnasium') : 'Gym information unavailable' }}</p></div><div class="calendar-controls"><a class="schedule-icon-button" aria-label="Previous month" href="{{ route('admin.schedule.index', [...$navigationFilters, 'month' => $previousMonth]) }}" @click="loading = true"><i class="bi bi-chevron-left" aria-hidden="true"></i></a><a class="today-button" href="{{ route('admin.schedule.index', [...$navigationFilters, 'month' => now()->format('Y-m'), 'date' => now()->toDateString()]) }}">Today</a><a class="schedule-icon-button" aria-label="Next month" href="{{ route('admin.schedule.index', [...$navigationFilters, 'month' => $nextMonth]) }}" @click="loading = true"><i class="bi bi-chevron-right" aria-hidden="true"></i></a></div></div>
            <div class="schedule-legend" aria-label="Schedule legend">@foreach(['available' => 'Available (green)', 'partial' => 'Partly reserved / available time (yellow)', 'occupied' => 'Fully booked / closed / unavailable (red)'] as $state => $label)<span><i class="schedule-dot {{ $state }}" aria-hidden="true"></i>{{ $label }}</span>@endforeach</div>
            @if(!$hours)<p class="schedule-warning" role="status">Operating hours are not configured correctly. Availability cannot be confirmed.</p>@endif
            <div class="calendar-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
            <div class="calendar-grid">
                @for($i = 0; $i < $leadingDays; $i++)<span class="calendar-blank" aria-hidden="true"></span>@endfor
                @foreach($days as $date => $day)
                <button class="calendar-day {{ $day['state'] }} availability-{{ $day['calendarState'] }} {{ $day['today'] ? 'is-today' : '' }}" :class="{ 'is-selected': selected === @js($date) }" type="button" @click="selectDate(@js($date))" :aria-pressed="selected === @js($date)" aria-label="{{ $day['label'] }}: {{ $day['stateLabel'] }}; {{ $day['approvedCount'] }} approved, {{ $day['pendingCount'] }} pending requests{{ $day['conflictCount'] ? '; overlapping requests or blocked bookings' : '' }}">
                    <span class="day-number">{{ $day['number'] }}@if($day['today'])<span class="today-marker">Today</span>@endif</span>
                    <span class="day-state">{{ $day['calendarLabel'] }}</span>
                    <span class="day-indicators">@if($day['hasAvailable'])<i class="schedule-dot available" title="Available interval" aria-hidden="true"></i>@endif @if($day['approvedCount'])<i class="schedule-dot occupied" title="Approved booking" aria-hidden="true"></i>@endif @if($day['pendingCount'])<i class="schedule-dot pending" title="Pending request" aria-hidden="true"></i>@endif @if($day['hasClosed'])<i class="schedule-dot closed" title="Closed interval" aria-hidden="true"></i>@endif</span>
                    @if(count($day['issues']))<span class="calendar-conflict"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span class="sr-only">Overlap or schedule block conflict</span></span>@endif
                </button>
                @endforeach
            </div>
            <p class="calendar-footnote">Select a date to review exact intervals. Availability uses configured operating hours and existing start/end conflict rules; setup and cleanup fields do not extend reserved intervals in the current workflow.</p>
        </section>
        <section class="card slot-panel" aria-labelledby="slot-heading">
            <div class="schedule-panel-heading"><div><p class="schedule-eyebrow">SELECTED DATE</p><h2 id="slot-heading" x-text="day.label" aria-live="polite"></h2><p>{{ $hours ? 'Configured hours: '.$hours['label'] : 'Operating hours unavailable' }}</p></div><i class="bi bi-clock" aria-hidden="true"></i></div>
            <div class="slot-summary" aria-live="polite"><span><strong x-text="day.approvedCount"></strong> approved</span><span><strong x-text="day.pendingCount"></strong> awaiting decision</span><span x-show="day.conflictCount" class="conflict-text"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Overlap / block conflict</span></div>
            <section class="schedule-issues" aria-label="Schedule conflicts and required actions" x-show="day.issues.length">
                <h3><span x-text="day.issues.length"></span> issue(s) to review — resolve from here</h3>
                <div class="schedule-issue-scroll"><template x-for="(issue, issueIndex) in day.issues" :key="issueIndex"><article class="schedule-issue">
                    <strong x-text="issue.title"></strong><p x-text="issue.description"></p>
                    <template x-for="booking in issue.bookings" :key="booking.reference"><div><p><span x-text="booking.reference"></span> · <span x-text="booking.time"></span> · <span x-text="booking.status"></span></p><a :href="booking.url">Resolve Conflict / Reschedule <span x-text="booking.reference"></span></a></div></template>
                    <a href="#schedule-blocks" x-show="issue.blocked">Review schedule blocks</a>
                </article></template></div>
            </section>
            <p class="schedule-impact-note" x-show="!day.issues.length">No overlapping requests or block conflicts were detected for this date.</p>
            <div class="slot-scroll">
                <p class="schedule-empty" x-show="!day.facilities.length">Gym information is unavailable. Please contact the administrator.</p>
                <template x-for="facility in day.facilities" :key="facility.id"><div class="facility-slots"><h3 x-text="facility.name"></h3><template x-for="(interval, index) in facility.intervals" :key="index"><article class="slot-interval" :class="interval.state"><div class="slot-title"><strong x-text="interval.time"></strong><span class="interval-badge" x-text="interval.label"></span></div><p x-show="interval.reasons.length" x-text="interval.reasons.join(' · ')"></p><p x-show="interval.pendingCount"><i class="bi bi-hourglass-split" aria-hidden="true"></i> <span x-text="interval.pendingCount"></span> pending request(s) · slot remains available until approval</p><p x-show="interval.approvedCount"><i class="bi bi-calendar-check" aria-hidden="true"></i> <span x-text="interval.approvedCount"></span> approved / completed booking(s)</p><p x-show="interval.conflict" class="conflict-text"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Conflicting intervals — review before taking action.</p><div class="interval-references"><template x-for="booking in interval.bookings" :key="booking.reference"><a :href="booking.url"><span x-text="booking.reference"></span> <small x-text="booking.status"></small><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></template></div></article></template></div></template>
            </div>
            <p class="calendar-footnote">Pending requests may overlap. They are not temporary holds or guaranteed reservations. Review conflicts through reservation management.</p>
        </section>
    </div>
    </details>
    <section class="card schedule-table-panel" aria-labelledby="details-heading"><div class="schedule-panel-heading"><div><h2 id="details-heading">Reservation Details</h2><p x-text="showMonthTable ? 'Filtered reservations for {{ $monthLabel }}' : day.label"></p></div><button type="button" class="btn-secondary" @click="showMonthTable = !showMonthTable" :aria-pressed="showMonthTable" x-text="showMonthTable ? 'Show Selected Date' : 'Show Entire Month'"></button></div><div class="schedule-table-scroll"><table class="schedule-details-table"><thead><tr><th>Reference Number</th><th>Event Name</th><th>Event Type</th><th>Reservation Date</th><th>Start Time</th><th>End Time</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @foreach($reservations as $reservation)<tr x-show="showMonthTable || selected === @js($reservation->reservation_date->toDateString())"><td class="reference-cell">{{ $reservation->reference_number }}</td><td>{{ $reservation->event_name }}<small>{{ $reservation->facility?->facility_name ?? 'Unassigned' }}</small></td><td>{{ $reservation->event_type ?: 'Not specified' }}</td><td>{{ $reservation->reservation_date->format('M j, Y') }}</td><td>{{ \Carbon\Carbon::parse($reservation->start_time)->format('g:i A') }}</td><td>{{ \Carbon\Carbon::parse($reservation->end_time)->format('g:i A') }}</td><td><span class="schedule-status {{ in_array($reservation->status, ['approved', 'completed']) ? 'occupied' : (in_array($reservation->status, ['rejected', 'cancelled']) ? 'closed' : 'pending') }}">{{ ucwords(str_replace('_', ' ', $reservation->status)) }}</span></td><td><a class="schedule-detail-link" href="{{ route('admin.reservations.show', $reservation) }}" aria-label="View details for {{ $reservation->reference_number }}">View Details <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></td></tr>@endforeach
        <tr x-show="showMonthTable ? !tableDates.length : !tableDates.includes(selected)"><td colspan="8" class="schedule-empty">No reservations match the selected date and filters. This does not override the availability intervals shown above.</td></tr>
    </tbody></table></div></section>
    <section id="schedule-blocks" class="card schedule-block-panel"><div class="schedule-panel-heading"><div><h2>Schedule Blocks</h2><p>Block dates for maintenance, closures, and special events. Conflicting approved reservations will be cancelled and requestors emailed to reschedule.</p></div></div>
    <details class="block-editor" @if($errors->any()) open @endif><summary><i class="bi bi-calendar-minus" aria-hidden="true"></i> Block a Date or Time Interval</summary><form class="block-form" x-data="Object.assign(scheduleBlockDates(@js(old('starts_on', $selectedDate)), @js(old('ends_on', $selectedDate)), @js($maximumBlockDays)), scheduleImpactReview(@js(route('admin.schedule.blocks.preview'))))" method="POST" action="{{ route('admin.schedule.blocks.store') }}" @submit.prevent="reviewImpact($event)">@csrf
        <div><label for="block-title" class="field-label">Title <span aria-hidden="true">*</span></label><input id="block-title" name="title" value="{{ old('title') }}" required placeholder="Maintenance or school event" class="field"></div>
        <div><p class="field-label">Gym</p><p>MCST Gymnasium</p></div>
        <div><label for="block-start" class="field-label">Start Date <span aria-hidden="true">*</span></label><input id="block-start" name="starts_on" type="date" x-model="start" value="{{ old('starts_on', $selectedDate) }}" required class="field"></div><div><label for="block-end" class="field-label">End Date <span aria-hidden="true">*</span></label><input id="block-end" name="ends_on" type="date" x-model="end" :min="start" :max="maximumEnd" :aria-invalid="Boolean(error)" aria-describedby="block-range-help block-range-error" value="{{ old('ends_on', $selectedDate) }}" required class="field"><p id="block-range-help">Maximum {{ $maximumBlockDays }} days, including the start and end dates.</p><p id="block-range-error" role="alert" style="color: #b91c1c" x-show="error" x-text="error"></p>@error('ends_on')<p role="alert" style="color: #b91c1c">{{ $message }}</p>@enderror</div>
        <div><label for="block-start-time" class="field-label">Start Time (optional)</label><input id="block-start-time" name="start_time" type="time" value="{{ old('start_time') }}" class="field"></div><div><label for="block-end-time" class="field-label">End Time (optional)</label><input id="block-end-time" name="end_time" type="time" value="{{ old('end_time') }}" class="field"></div>
        <div class="block-form-wide"><label for="block-reason" class="field-label">Reason (optional)</label><textarea id="block-reason" name="reason" class="field" rows="2" placeholder="Explain why the schedule is blocked">{{ old('reason') }}</textarea></div><div class="block-form-wide block-actions"><p>Leave both times blank for an all-day closure. Enter both times to block an exact interval.</p><button class="btn-primary" type="submit" :disabled="impactBusy" x-text="impactBusy ? 'Reviewing…' : 'Review block and affected bookings'">Review block and affected bookings</button></div>
        @include('admin.schedule.partials.impact-review')
    </form></details>
    <div class="block-list">@forelse($blocks as $block)<div class="block-row"><div><h3>{{ $block->title }}</h3><p>{{ $block->starts_on->format('M j, Y') }} – {{ $block->ends_on->format('M j, Y') }} · {{ $block->facility?->facility_name ?? 'MCST Gymnasium' }} · {{ $block->start_time && $block->end_time ? \Carbon\Carbon::parse($block->start_time)->format('g:i A').' – '.\Carbon\Carbon::parse($block->end_time)->format('g:i A') : 'All day' }}</p></div><form method="POST" action="{{ route('admin.schedule.blocks.destroy', $block) }}" @submit="if (!confirm('Release this schedule block? Reservation conflicts and operating hours still apply.')) $event.preventDefault()">@csrf @method('DELETE')<button type="submit" class="btn-secondary">Release Block</button></form></div>@empty<p class="schedule-empty">No blocked dates or intervals in this month.</p>@endforelse</div></section>
</div>
<script src="{{ asset('js-admin-schedule-views.js') }}?v={{ filemtime(public_path('js-admin-schedule-views.js')) }}"></script>
<script>
function scheduleBlockDates(start, end, limit = 7) {
    return {
        start, end, limit,
        get maximumEnd() {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(this.start)) return '';
            const date = new Date(this.start + 'T00:00:00Z');
            date.setUTCDate(date.getUTCDate() + this.limit - 1);
            return date.toISOString().slice(0, 10);
        },
        get error() {
            if (!this.start || !this.end) return '';
            if (this.end < this.start) return 'End date must be on or after the start date.';
            return this.end > this.maximumEnd ? 'Schedule blocks can cover a maximum of ' + this.limit + ' days, including the start and end dates.' : '';
        }
    };
}
</script>
@endsection
