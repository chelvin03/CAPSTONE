@extends('layouts.app')
@section('title', 'Staff Dashboard')
@section('content')
<link rel="stylesheet" href="{{ asset('css/dashboard-reference.css') }}?v={{ filemtime(public_path('css/dashboard-reference.css')) }}">
<link rel="stylesheet" href="{{ asset('css/staff-dashboard.css') }}?v={{ filemtime(public_path('css/staff-dashboard.css')) }}">
<link rel="stylesheet" href="{{ asset('css/staff-dashboard-reference.css') }}?v={{ filemtime(public_path('css/staff-dashboard-reference.css')) }}">
@php
    $selectedYear = (string) ($filters['year'] ?? 'all');
    $canSelectDay = $selectedYear !== 'all' && isset($filters['month']);
    $monthDays = $canSelectDay ? \Carbon\CarbonImmutable::create((int) $selectedYear, (int) $filters['month'], 1)->daysInMonth : 0;
@endphp
<div class="reference-dashboard staff-dashboard staff-reference-dashboard">
    <header class="reference-heading">
        <div><p class="reference-eyebrow">MCST GYMNASIUM</p><h1>Staff Dashboard</h1><p>Welcome, {{ auth()->user()->full_name }}.</p><p>Monitor gym schedules, upcoming events, equipment readiness, and acknowledgement receipts.</p></div>
        <div class="staff-header-date"><strong>{{ $now->format('l, F j, Y') }}</strong><span>{{ $now->format('g:i A') }} ({{ config('app.timezone') }})</span></div>
    </header>

    <details class="staff-filter-section staff-compact-filters" aria-labelledby="staff-filter-title"><summary id="staff-filter-title">Reporting filters · {{ $selectedYear === 'all' ? 'All years' : $selectedYear }}{{ isset($filters['month']) ? ' / '.$filters['month'] : '' }}{{ isset($filters['day']) ? ' / '.$filters['day'] : '' }}</summary>

        <p>Filters apply to AR tasks, Approved Events, upcoming events and status overview. Today's events, next-seven-day bookings, availability and equipment reflect current operations.</p>
        @if($errors->any())<div class="alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="GET" action="{{ route('staff.dashboard') }}" class="reference-date-selectors staff-filters">
            <label for="staff-year">Year<select id="staff-year" name="year" onchange="this.form.elements.day.value = ''; this.form.requestSubmit()"><option value="all" @selected($selectedYear === 'all')>All years</option>@foreach($years as $year)<option value="{{ $year }}" @selected($selectedYear === (string) $year)>{{ $year }}</option>@endforeach</select></label>
            <label for="staff-month">Month<select id="staff-month" name="month" onchange="this.form.elements.day.value = ''; this.form.requestSubmit()"><option value="">All months</option>@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected((int)($filters['month'] ?? 0) === $month)>{{ \Carbon\CarbonImmutable::create(2000, $month, 1)->format('F') }}</option>@endforeach</select></label>
            <label for="staff-day">Day<select id="staff-day" name="day" @disabled(!$canSelectDay) onchange="this.form.requestSubmit()"><option value="">All days</option>@if($canSelectDay)@foreach(range(1, $monthDays) as $day)<option value="{{ $day }}" @selected((int)($filters['day'] ?? 0) === $day)>{{ $day }}</option>@endforeach @endif</select></label>
            <button type="submit" class="btn-primary">Apply Filters</button><a class="staff-reset" href="{{ route('staff.dashboard') }}">Reset</a>
        </form>
        <p class="staff-period">Showing: {{ $selectedYear === 'all' ? 'All years' : $selectedYear }} &middot; {{ isset($filters['month']) ? \Carbon\CarbonImmutable::create(2000, (int)$filters['month'], 1)->format('F') : 'All months' }} &middot; {{ isset($filters['day']) ? 'Day '.$filters['day'] : 'All days' }}. Choose a year and month to filter by day.</p>
    </details>
    <section class="reference-stats staff-seven-kpis" aria-label="Operational and acknowledgement receipt summary">
        @foreach([
            ["Today's Events", number_format($todayCount), 'blue', 'bi-calendar-event', 'Approved or completed events scheduled today.'],
            ['Upcoming Reservations', number_format($upcomingCount), 'green', 'bi-calendar-week', 'Approved bookings tomorrow through '.\Carbon\CarbonImmutable::parse($sevenDaysThrough)->format('M j').'.'],
            ['Gym Availability', $gymAvailability, 'blue', 'bi-building', ($facilities->first()?->facility_name ?? 'Gym information unavailable').'. Scheduled status; no live presence tracking.'],
            ['Equipment Status', number_format($equipmentCounts->get('available',0)), 'amber', 'bi-tools', $equipmentAttention.' needing attention · '.$equipmentCounts->sum().' total records.'],
            ['Pending AR', number_format($pendingArCount), 'rose', 'bi-file-earmark-text', 'Approved reservations requiring acknowledgement receipt.'],
            ['Completed AR', number_format($completedArCount), 'green', 'bi-file-earmark-check', 'Generated ARs marked completed.'],
            ['Approved Events', number_format($approvedPeriodCount), 'purple', 'bi-calendar-check', 'Total approved reservations for this period.'],
        ] as [$title, $value, $tone, $icon, $detail])
        <article class="reference-stat tone-{{ $tone }}"><span class="reference-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span><div><h2>{{ $title }}</h2><p class="reference-value @if($title === 'Gym Availability') staff-availability-value @endif">@if($title === 'Gym Availability')<span class="staff-availability staff-availability-{{ strtolower($gymAvailability) }}">{{ $value }}</span>@else{{ $value }}@if($title === 'Equipment Status') <small>available</small>@endif @endif</p><p class="reference-detail">{{ $detail }}</p></div></article>
        @endforeach
    </section>
    <section class="reference-panel staff-ar-panel" aria-labelledby="staff-ar-title">
        <header class="staff-panel-heading"><div><h2 id="staff-ar-title">Approved Reservations — AR Required</h2><p>List of approved reservations that need an acknowledgement receipt.</p></div><a class="btn-primary" href="{{ route('staff.ar.index') }}">View All</a></header>
        @include('staff.partials.ar-table', ['rows' => $arReservations->take(2)])
        <p class="reference-note">Showing {{ min(2, $arReservations->count()) }} of {{ $approvedPeriodCount }} approved reservations in this period. View All to manage every AR. Nearest upcoming tasks appear first.</p>
    </section>
    <div class="reference-panels staff-monitoring-panels staff-schedule-status">
    <section class="reference-panel staff-today" aria-labelledby="staff-today-title">
        <header><h2 id="staff-today-title">Today's Gymnasium Schedule</h2><p>{{ $now->format('F j, Y') }} &middot; Approved and completed events, ordered by start time.</p></header>
        @include('staff.partials.today-schedule', ['rows' => $todayReservations])
        @if($todayReservations->hasPages())<div class="staff-pagination">{{ $todayReservations->links() }}</div>@endif
    </section>
    <section class="reference-panel" aria-labelledby="staff-status-title">
        <header><h2 id="staff-status-title">Reservation Status Overview</h2><p>Actual reservation counts in the selected reporting period.</p></header>
        @php($statusOffset = 0)
        <div class="reference-status-body">
            <div class="reference-donut"><svg viewBox="0 0 120 120" role="img" aria-label="{{ $statusTotal }} reservations; status counts and percentages listed alongside"><circle cx="60" cy="60" r="45" fill="none" stroke="#e2e8f0" stroke-width="15"/>
            @foreach($statusBreakdown as $item)
                @php($share = $statusTotal ? $item['count'] / $statusTotal * 100 : 0)
                @if($share)<circle cx="60" cy="60" r="45" fill="none" stroke="{{ $item['color'] }}" stroke-width="15" pathLength="100" stroke-dasharray="{{ $share }} {{ 100-$share }}" stroke-dashoffset="{{ -$statusOffset }}" transform="rotate(-90 60 60)"/>@endif
                @php($statusOffset += $share)
            @endforeach
            </svg><div><span>Total Reservations</span><strong>{{ number_format($statusTotal) }}</strong></div></div>
            <ul class="reference-status-legend">@foreach($statusBreakdown as $item)<li><span><i style="background:{{ $item['color'] }}" aria-hidden="true"></i>{{ $item['label'] }}</span><small>{{ $item['percent'] }}%</small><strong>{{ number_format($item['count']) }}</strong></li>@endforeach</ul>
        </div>
        @if(!$statusTotal)<p class="staff-empty">No reservations match the selected period.</p>@endif
    </section>
    </div>
    <details class="staff-additional-operations"><summary>Additional operational details</summary>
    <section class="reference-panel staff-facility-details"><header><h2>Gym Readiness</h2><p>Stored status and scheduled activity.</p></header><div class="staff-readiness">
        @forelse($facilities as $facility)<p><strong>{{ $facility->facility_name }}</strong> · <x-operational-status :status="$facility->status" /> · {{ $facility->blocked_now ? 'Schedule block in effect.' : ($facility->scheduled_now ? 'In use by approved schedule.' : ($facility->reserved_today ? 'Reserved later today.' : 'No remaining approved bookings today.')) }}</p>@empty<p>Gym information is unavailable.</p>@endforelse
        <p>Equipment: {{ $equipmentCounts->get('maintenance',0) }} under maintenance · {{ $equipmentCounts->get('unavailable',0) }} unavailable. Counts are records, not units.</p>
    </div></section>
        <section class="reference-panel" aria-labelledby="staff-attention-title">
            <header><h2 id="staff-attention-title">Attention Needed</h2><p>Current equipment, facility, and scheduling notices.</p></header>
            <ul class="staff-notices">@forelse($notices as $notice)<li><i class="bi bi-exclamation-circle" aria-hidden="true"></i><a href="{{ $notice['url'] }}">{{ $notice['text'] }}</a></li>@empty<li class="staff-empty">No operational notices at this time.</li>@endforelse</ul>
            @if($notices->isNotEmpty())<p class="reference-note">Attendance notices cover today and the next seven days. Cancellations and schedule changes cover the last seven days.</p>@endif
        </section>
    <section class="reference-panel staff-upcoming" aria-labelledby="staff-upcoming-title">
        <header><h2 id="staff-upcoming-title">Upcoming Gymnasium Events</h2><p>Approved reservations after today in the selected period, nearest event first.</p></header>
        @include('staff.partials.dashboard-events', ['rows' => $upcomingReservations, 'caption' => 'Upcoming Gymnasium Events', 'showPreparation' => false, 'emptyMessage' => 'No upcoming approved reservations match the selected period.'])
        @if($upcomingReservations->hasPages())<div class="staff-pagination">{{ $upcomingReservations->links() }}</div>@endif
    </section>
</details></div>
@endsection
