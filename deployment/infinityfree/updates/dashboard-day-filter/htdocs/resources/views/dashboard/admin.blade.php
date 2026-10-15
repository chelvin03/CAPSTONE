@extends('layouts.app')
@section('title', 'Gymnasium Business Intelligence')
@section('content')
<link rel="stylesheet" href="{{ asset('css/dashboard-reference.css') }}?v={{ filemtime(public_path('css/dashboard-reference.css')) }}">
@php
    $label = fn ($value) => \App\Services\DashboardAnalyticsService::label($value);
    $segments = $statusBreakdown->where('count', '>', 0);
    $offset = 0;
    $selectedYear = (string) ($filters['year'] ?? \Carbon\CarbonImmutable::parse($filters['date_to'])->year);
    $canSelectDay = $selectedYear !== 'all' && isset($filters['month']);
    $monthDays = $canSelectDay ? \Carbon\CarbonImmutable::create((int)$selectedYear, (int)$filters['month'], 1)->daysInMonth : 0;
@endphp
<div class="reference-dashboard">
    <header class="reference-heading">
        <div><p class="reference-eyebrow">MCST GYMNASIUM</p><h1>Business Intelligence Dashboard</h1><p>Monitor reservation activity, gym availability, and booking demand.</p></div>
        <a class="btn-primary" href="{{ route('admin.reservations.index') }}">Manage Reservations</a>
    </header>
    @if ($errors->any())
        <div class="alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="reference-toolbar">
        <form method="GET" action="{{ route('admin.dashboard') }}" class="reference-date-selectors">
            <label for="dashboard-year">Year<select id="dashboard-year" name="year" onchange="this.form.elements.day.value = ''; this.form.requestSubmit()">
                <option value="all" @selected($selectedYear === 'all')>All years</option>
                @foreach($years as $year)<option value="{{ $year }}" @selected($selectedYear === (string) $year)>{{ $year }}</option>@endforeach
            </select></label>
            <label for="dashboard-month">Month<select id="dashboard-month" name="month" onchange="this.form.elements.day.value = ''; this.form.requestSubmit()">
                <option value="">All months</option>
                @foreach(range(1,12) as $month)<option value="{{ $month }}" @selected((int)($filters['month'] ?? 0) === $month)>{{ \Carbon\CarbonImmutable::create(2000,$month,1)->format('F') }}</option>@endforeach
            </select></label>
            <label for="dashboard-day">Day<select id="dashboard-day" name="day" @disabled(!$canSelectDay) onchange="this.form.requestSubmit()" @if(!$canSelectDay) title="Choose a specific year and month first" @endif>
                <option value="">All days</option>
                @if($canSelectDay)
                    @foreach(range(1,$monthDays) as $day)<option value="{{ $day }}" @selected((int)($filters['day'] ?? 0) === $day)>{{ $day }}</option>@endforeach
                @endif
            </select></label>
            <noscript><button class="btn-primary" type="submit">Apply</button></noscript>
        </form>
    </div>
    <p class="reference-period">{{ \Carbon\CarbonImmutable::parse($filters['date_from'])->format('M j, Y') }}–{{ \Carbon\CarbonImmutable::parse($filters['date_to'])->format('M j, Y') }}@if(isset($filters['month'])) · {{ \Carbon\CarbonImmutable::create(2000,(int)$filters['month'],1)->format('F') }} only @endif</p>
    <section class="reference-stats" aria-label="Reservation summary">
        @foreach([
            ['Total Reservations', number_format($totalReservations), 'bi-calendar2-week', 'blue', 'All recorded statuses in this period'],
            ['Approved Reservations', number_format($approvedReservations), 'bi-check-circle', 'green', 'Confirmed bookings'],
            ['Pending Review', number_format($pendingReservations), 'bi-hourglass-split', 'amber', 'New and validated requests'],
            ['Gym Utilization', $utilizationRate === null ? 'N/A' : $utilizationRate.'%', 'bi-clock', 'orange', 'Approved hours / bookable hours'],
            ['Most Common Event', $mostCommonEventType, 'bi-trophy', 'rose', 'Most recorded event type in this period'],
        ] as [$title,$value,$icon,$tone,$detail])
            <article class="reference-stat tone-{{ $tone }}"><span class="reference-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span><div><h2>{{ $title }}</h2><p class="reference-value">{{ $value }}</p><p class="reference-detail">{{ $detail }}</p></div></article>
        @endforeach
    </section>
    <div class="reference-panels">
        <section class="reference-panel">
            <header><h2>Booking Activity</h2><p>Summary for the selected reporting period.</p></header>
            <dl class="reference-activity">
                <div><dt>Completed events</dt><dd>{{ number_format($completedEvents) }}</dd></div>
                <div><dt>Cancelled reservations</dt><dd>{{ number_format($cancelledReservations) }}</dd></div>
                <div><dt>Requests overdue for review</dt><dd>{{ number_format($oldPending) }}</dd></div>
                <div><dt>Expected attendees</dt><dd>{{ number_format($expectedAttendees) }}</dd></div>
                <div><dt>Average processing time</dt><dd>{{ $processingHours === null ? 'N/A' : $processingHours.' hrs' }}</dd></div>
            </dl>
            <p class="reference-note">Expected attendance covers approved and completed events. Utilization reflects scheduled approved hours, with overlaps merged and blackout periods excluded.</p>
        </section>
        <section class="reference-panel">
            <header><h2>Reservation Status Overview</h2><p>Counts and share of reservations in this period.</p></header>
            <div class="reference-status-body">
                <div class="reference-donut" role="img" aria-label="{{ number_format($totalReservations) }} reservations; status counts and percentages are listed alongside.">
                    <svg viewBox="0 0 120 120" aria-hidden="true">
                        <circle cx="60" cy="60" r="45" fill="none" stroke="#e2e8f0" stroke-width="15" />
                        @foreach($segments as $segment)
                            @php($length = $segment['count'] / $totalReservations * 100)
                            <circle cx="60" cy="60" r="45" fill="none" stroke="{{ $statusColors[$segment['status']] ?? '#64748b' }}" stroke-width="15" pathLength="100" stroke-dasharray="{{ $length }} {{ 100-$length }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 60 60)" />
                            @php($offset += $length)
                        @endforeach
                    </svg>
                    <div><span>Total Reservations</span><strong>{{ number_format($totalReservations) }}</strong></div>
                </div>
                <ul class="reference-status-legend" aria-label="Reservation status counts">
                    @foreach($statusBreakdown as $row)
                        <li><span><i style="background: {{ $statusColors[$row['status']] ?? '#64748b' }}" aria-hidden="true"></i>{{ $label($row['status']) }}</span><small>{{ $row['percentage'] === null ? '—' : $row['percentage'].'%' }}</small><strong>{{ number_format($row['count']) }}</strong></li>
                    @endforeach
                </ul>
            </div>
            @if(!$totalReservations)<p class="reference-note">No reservations in this period. Choose another month or year.</p>@endif
        </section>
    </div>
    @include('dashboard.partials.trends')
    <p class="reference-updated">Last updated: {{ $generatedAt->format('M j, Y g:i A T') }}</p>
</div>
@endsection
