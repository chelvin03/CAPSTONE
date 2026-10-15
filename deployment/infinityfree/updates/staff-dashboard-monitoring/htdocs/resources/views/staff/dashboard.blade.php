@extends('layouts.app')
@section('title', 'Staff Dashboard')
@section('content')
<link rel="stylesheet" href="{{ asset('css/dashboard-reference.css') }}?v={{ filemtime(public_path('css/dashboard-reference.css')) }}">
<link rel="stylesheet" href="{{ asset('css/staff-dashboard.css') }}?v={{ filemtime(public_path('css/staff-dashboard.css')) }}">
@php
    $selectedYear = (string) ($filters['year'] ?? 'all');
    $canSelectDay = $selectedYear !== 'all' && isset($filters['month']);
    $monthDays = $canSelectDay ? \Carbon\CarbonImmutable::create((int) $selectedYear, (int) $filters['month'], 1)->daysInMonth : 0;
@endphp
<div class="reference-dashboard staff-dashboard">
    <header class="reference-heading">
        <div><p class="reference-eyebrow">MCST GYMNASIUM</p><h1>Staff Dashboard</h1><p>Welcome, {{ auth()->user()->full_name }}.</p><p>Monitor gym schedules, upcoming events, and equipment readiness.</p></div>
        <span class="staff-readonly"><i class="bi bi-eye" aria-hidden="true"></i> View-only monitoring</span>
    </header>
    <p class="reference-updated">{{ $now->format('l, F j, Y') }} &middot; Updated {{ $now->format('g:i A') }} ({{ config('app.timezone') }})</p>
    <section class="reference-stats" aria-label="Live operational summary">
        <article class="reference-stat tone-blue"><span class="reference-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span><div><h2>Today's Events</h2><p class="reference-value">{{ number_format($todayCount) }}</p><p class="reference-detail">Approved or completed events scheduled today.</p></div></article>
        <article class="reference-stat tone-green"><span class="reference-icon"><i class="bi bi-calendar-week" aria-hidden="true"></i></span><div><h2>Upcoming Reservations</h2><p class="reference-value">{{ number_format($upcomingCount) }}</p><p class="reference-detail">Approved bookings tomorrow through {{ \Carbon\CarbonImmutable::parse($sevenDaysThrough)->format('M j') }}.</p></div></article>
        <article class="reference-stat tone-blue"><span class="reference-icon"><i class="bi bi-building" aria-hidden="true"></i></span><div><h2>Gym Availability</h2>
            @forelse($facilities as $facility)
                <x-operational-status :status="$facility->status" />
                <p class="reference-detail">{{ $facility->facility_name }}</p>
                <p class="reference-detail">{{ $facility->blocked_now ? 'Schedule block in effect.' : ($facility->scheduled_now ? 'In use by approved schedule.' : ($facility->reserved_today ? 'Reserved later today.' : 'No remaining approved bookings today.')) }}</p>
            @empty<p class="reference-detail">Gym information is unavailable.</p>@endforelse
            <p class="reference-detail">Stored facility status and schedule; live presence is not tracked.</p>
        </div></article>
        <article class="reference-stat tone-amber"><span class="reference-icon"><i class="bi bi-tools" aria-hidden="true"></i></span><div><h2>Equipment Status</h2><p class="reference-value">{{ number_format($equipmentCounts->get('available', 0)) }} <small>available</small></p><p class="reference-detail"><strong>{{ number_format($equipmentAttention) }} needing attention</strong> &middot; {{ number_format($equipmentCounts->sum()) }} total records.</p><p class="reference-detail">{{ $equipmentCounts->get('maintenance', 0) }} under maintenance &middot; {{ $equipmentCounts->get('unavailable', 0) }} unavailable. Counts are equipment records, not units.</p></div></article>
    </section>
    <section class="reference-panel staff-today" aria-labelledby="staff-today-title">
        <header><h2 id="staff-today-title">Today's Gymnasium Schedule</h2><p>{{ $now->format('F j, Y') }} &middot; Approved and completed events, ordered by start time.</p></header>
        @include('staff.partials.dashboard-events', ['rows' => $todayReservations, 'caption' => "Today's Gymnasium Schedule", 'showPreparation' => true, 'emptyMessage' => 'No gymnasium events are scheduled for today.'])
        @if($todayReservations->hasPages())<div class="staff-pagination">{{ $todayReservations->links() }}</div>@endif
    </section>
    <section class="staff-filter-section" aria-labelledby="staff-filter-title">
        <h2 id="staff-filter-title">Browse Reservation Activity</h2>
        <p>Filters apply to upcoming events and the status overview. Summary cards and today's schedule always show current operations.</p>
        @if($errors->any())<div class="alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="GET" action="{{ route('staff.dashboard') }}" class="reference-date-selectors staff-filters">
            <label for="staff-year">Year<select id="staff-year" name="year" onchange="this.form.elements.day.value = ''; this.form.requestSubmit()"><option value="all" @selected($selectedYear === 'all')>All years</option>@foreach($years as $year)<option value="{{ $year }}" @selected($selectedYear === (string) $year)>{{ $year }}</option>@endforeach</select></label>
            <label for="staff-month">Month<select id="staff-month" name="month" onchange="this.form.elements.day.value = ''; this.form.requestSubmit()"><option value="">All months</option>@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected((int)($filters['month'] ?? 0) === $month)>{{ \Carbon\CarbonImmutable::create(2000, $month, 1)->format('F') }}</option>@endforeach</select></label>
            <label for="staff-day">Day<select id="staff-day" name="day" @disabled(!$canSelectDay) onchange="this.form.requestSubmit()"><option value="">All days</option>@if($canSelectDay)@foreach(range(1, $monthDays) as $day)<option value="{{ $day }}" @selected((int)($filters['day'] ?? 0) === $day)>{{ $day }}</option>@endforeach @endif</select></label>
            <button type="submit" class="btn-primary">Apply Filters</button><a class="staff-reset" href="{{ route('staff.dashboard') }}">Reset</a>
        </form>
        <p class="staff-period">Showing: {{ $selectedYear === 'all' ? 'All years' : $selectedYear }} &middot; {{ isset($filters['month']) ? \Carbon\CarbonImmutable::create(2000, (int)$filters['month'], 1)->format('F') : 'All months' }} &middot; {{ isset($filters['day']) ? 'Day '.$filters['day'] : 'All days' }}. Choose a year and month to filter by day.</p>
    </section>
    <div class="reference-panels staff-monitoring-panels">
        <section class="reference-panel" aria-labelledby="staff-status-title">
            <header><h2 id="staff-status-title">Reservation Status Overview</h2><p>{{ number_format($statusTotal) }} reservations in the selected period &middot; All recorded statuses.</p></header>
            <div class="staff-status-chart" role="list" aria-label="Reservation counts by status">
                @if($statusTotal === 0)<p class="staff-empty">No reservations match the selected period.</p>@endif
                @foreach($statusBreakdown as $item)
                    <div class="staff-status-row" role="listitem"><div class="staff-status-label"><span>{{ $item['label'] }}</span><strong>{{ number_format($item['count']) }} <small>({{ $item['percent'] }}%)</small></strong></div><div class="staff-status-track" aria-hidden="true"><span style="width: {{ $item['percent'] }}%; background-color: {{ $item['color'] }}"></span></div></div>
                @endforeach
            </div>
        </section>
        <section class="reference-panel" aria-labelledby="staff-attention-title">
            <header><h2 id="staff-attention-title">Attention Needed</h2><p>Current equipment, facility, and scheduling notices.</p></header>
            <ul class="staff-notices">@forelse($notices as $notice)<li><i class="bi bi-exclamation-circle" aria-hidden="true"></i><a href="{{ $notice['url'] }}">{{ $notice['text'] }}</a></li>@empty<li class="staff-empty">No operational notices at this time.</li>@endforelse</ul>
            @if($notices->isNotEmpty())<p class="reference-note">Attendance notices cover today and the next seven days. Cancellations and schedule changes cover the last seven days.</p>@endif
        </section>
    </div>
    <section class="reference-panel staff-upcoming" aria-labelledby="staff-upcoming-title">
        <header><h2 id="staff-upcoming-title">Upcoming Gymnasium Events</h2><p>Approved reservations after today in the selected period, nearest event first.</p></header>
        @include('staff.partials.dashboard-events', ['rows' => $upcomingReservations, 'caption' => 'Upcoming Gymnasium Events', 'showPreparation' => false, 'emptyMessage' => 'No upcoming approved reservations match the selected period.'])
        @if($upcomingReservations->hasPages())<div class="staff-pagination">{{ $upcomingReservations->links() }}</div>@endif
    </section>
</div>
@endsection
