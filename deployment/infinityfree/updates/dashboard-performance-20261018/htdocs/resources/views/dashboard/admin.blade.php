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
<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}?v={{ filemtime(public_path('css/admin-dashboard.css')) }}">
<link rel="stylesheet" href="{{ asset('css/decision-support.css') }}?v={{ filemtime(public_path('css/decision-support.css')) }}">
<div class="reference-dashboard admin-bi-dashboard"><div class="admin-topbar">
    <header class="reference-heading">
        <div><p class="reference-eyebrow">MCST GYMNASIUM</p><h1>Business Intelligence Dashboard</h1><p>Monitor reservation activity, gym utilization, and key insights for decision-making.</p></div>

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
            <a class="btn-primary admin-manage" href="{{ route('admin.reservations.index') }}">Manage Reservations</a><details class="admin-advanced"><summary>More filters</summary><div class="admin-advanced-fields">
            @foreach(['category' => ['Event Type', $categories], 'status' => ['Reservation Status', $statusOptions], 'requestor_type' => ['Requestor Type', $requestorTypes]] as $name => [$caption, $options])
<label>{{ $caption }}<select name="{{ $name }}" onchange="this.form.requestSubmit()"><option value="">All</option>@foreach($options as $option)<option value="{{ $option }}" @selected(($filters[$name] ?? '') === $option)>{{ $label($option) }}</option>@endforeach</select></label>
@endforeach
<label>Heatmap demand<select name="heat_scope" onchange="this.form.requestSubmit()"><option value="approved">Approved by default</option><option value="all" @selected(($filters['heat_scope'] ?? '') === 'all')>All Statuses</option></select></label>
</div></details><noscript><button class="btn-primary" type="submit">Apply</button></noscript>
        </form>
    </div>
    </div><p class="reference-period">{{ \Carbon\CarbonImmutable::parse($filters['date_from'])->format('M j, Y') }}–{{ \Carbon\CarbonImmutable::parse($filters['date_to'])->format('M j, Y') }}@if(isset($filters['month'])) · {{ \Carbon\CarbonImmutable::create(2000,(int)$filters['month'],1)->format('F') }} only @endif</p>
    <section class="reference-stats" aria-label="Reservation summary">
        @foreach([
            ['Total Reservations', number_format($totalReservations), 'bi-calendar2-week', 'blue', 'All recorded statuses in this period'],
            ['Approved Reservations', number_format($approvedReservations), 'bi-check-circle', 'green', 'Confirmed bookings'],
            ['Pending Review', number_format($pendingReservations), 'bi-hourglass-split', 'amber', 'New and validated requests'],
            ['Gym Utilization', $utilizationRate === null ? 'N/A' : $utilizationRate.'%', 'bi-clock', 'orange', 'Approved hours / bookable hours'],
            ['Cancellation Rate', number_format($cancellationRate ?? 0, 1).'%', 'bi-people', 'rose', $cancelledReservations.' cancelled out of '.$totalReservations.' reservations'],
            ['Data Completeness', $completenessRate.'%', 'bi-file-earmark-text', 'purple', $completeCount.' complete / '.$totalReservations.' reservations'],
        ] as [$title,$value,$icon,$tone,$detail])
            <article class="reference-stat tone-{{ $tone }}"><span class="reference-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span><div><h2>{{ $title }}</h2><p class="reference-value">{{ $value }}</p><p class="reference-detail">{{ $detail }}</p></div></article>
        @endforeach
    </section>
    @include('dashboard.partials.decision-support')
    @include('dashboard.partials.insights-v2')
    @include('dashboard.partials.performance')
</div>
@endsection
