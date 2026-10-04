@extends('layouts.app')
@section('title', 'Gymnasium Business Intelligence')
@section('content')
@php
    $label = fn ($value) => \App\Services\DashboardAnalyticsService::label($value);
    $selectedYear = request('year', $filters['year'] ?? '');
    $segments = $statusBreakdown->where('count', '>', 0);
    $offset = 0;
@endphp
<div class="page-shell bi-dashboard max-w-none">
    <header class="bi-header">
        <div>
            <h1 class="page-title">Gymnasium Business Intelligence</h1>
            <p class="page-description">Monitor gymnasium reservations, utilization, and event activities.</p>
        </div>
        <form method="GET" action="{{ route('admin.dashboard') }}" class="bi-filters" x-data="{ loading: false }" @submit="loading = true" :aria-busy="loading">
            <label>Year<select name="year" class="field">
                <option value="all" @selected($selectedYear === 'all')>All Years</option>
                @foreach ($years as $year)<option value="{{ $year }}" @selected((string) $selectedYear === (string) $year)>{{ $year }}</option>@endforeach
            </select></label>
            <label>Month<select name="month" class="field">
                <option value="">All Months</option>
                @for ($month = 1; $month <= 12; $month++)<option value="{{ $month }}" @selected((int) ($filters['month'] ?? 0) === $month)>{{ \Carbon\CarbonImmutable::create(2000, $month, 1)->format('F') }}</option>@endfor
            </select></label>
            <label>Reservation Status<select name="status" class="field">
                <option value="">All Statuses</option>
                @foreach ($statusOptions as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label($status) }}</option>@endforeach
            </select></label>
            <label>Event Type<select name="category" class="field">
                <option value="">All Event Types</option>
                @foreach ($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>@endforeach
            </select></label>
            <div class="bi-filter-actions">
                <button class="btn-primary" :disabled="loading"><span x-show="!loading">Apply Filters</span><span x-show="loading" x-cloak role="status">Loading…</span></button>
                <a href="{{ route('admin.dashboard') }}" class="bi-link">Reset Filters</a>
            </div>
        </form>
    </header>
    @if ($errors->any())
        <div class="alert-error" role="alert"><div><strong>Please check your filters.</strong><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif
    <p class="bi-period">{{ \Carbon\CarbonImmutable::parse($filters['date_from'])->format('M d, Y') }} – {{ \Carbon\CarbonImmutable::parse($filters['date_to'])->format('M d, Y') }}@if(isset($filters['month'])) · {{ \Carbon\CarbonImmutable::create(2000, (int) $filters['month'], 1)->format('F') }} only @endif</p>
    <section class="bi-stats" aria-label="Reservation summary">
        <article class="card bi-stat"><span class="bi-stat-icon bi-blue"><i class="bi bi-calendar2-week" aria-hidden="true"></i></span><h2>Total Reservations</h2><p class="bi-stat-value">{{ number_format($totalReservations) }}</p><p class="bi-stat-detail">Matching reservation records</p></article>
        <article class="card bi-stat"><span class="bi-stat-icon bi-green"><i class="bi bi-check-circle" aria-hidden="true"></i></span><h2>Approved Reservations</h2><p class="bi-stat-value">{{ number_format($approvedReservations) }}</p><p class="bi-stat-detail">Approved status</p></article>
        <article class="card bi-stat"><span class="bi-stat-icon bi-amber"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span><h2>Pending Reservations</h2><p class="bi-stat-value">{{ number_format($pendingReservations) }}</p><p class="bi-stat-detail">New + Validated reservations</p></article>
        <article class="card bi-stat"><span class="bi-stat-icon bi-blue"><i class="bi bi-clock" aria-hidden="true"></i></span><h2>Gymnasium Utilization Rate</h2><p class="bi-stat-value">{{ $utilizationRate === null ? 'N/A' : $utilizationRate.'%' }}</p><p class="bi-stat-detail">{{ $availableHours === null ? 'Operating hours or facility unavailable' : ($availableHours == 0 ? 'No bookable hours in this period' : $utilizedHours.' booked / '.$availableHours.' bookable hours') }}</p></article>
        <article class="card bi-stat"><span class="bi-stat-icon bi-blue"><i class="bi bi-trophy" aria-hidden="true"></i></span><h2>Most Common Event Type</h2><p class="bi-stat-value bi-event-value">{{ $mostCommonEventType }}</p><p class="bi-stat-detail">{{ $eventDemand->isEmpty() ? 'No matching reservations' : $eventDemand->first()['count'].' reservations · ties sorted alphabetically' }}</p></article>
    </section>
    <div class="bi-panels">
        <section class="card bi-overview" aria-labelledby="overview-title">
            <header class="bi-panel-header"><div><h2 id="overview-title">Reservation Overview</h2><p>Summary of reservation activities.</p></div><a href="{{ route('admin.reservations.index', \Illuminate\Support\Arr::except($filters, ['year', 'grouping', 'period'])) }}" class="bi-link">View All <i class="bi bi-arrow-right" aria-hidden="true"></i></a></header>
            <div class="overflow-x-auto">
                <table class="bi-table">
                    <caption class="sr-only">Latest five matching reservations, ordered by reservation date and start time descending.</caption>
                    <thead><tr>@foreach (['Reference Number', 'Event Name', 'Event Type', 'Reservation Date', 'Time', 'Status'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>@forelse ($overviewReservations as $reservation)
                        <tr><td><a class="bi-link" href="{{ route('admin.reservations.show', $reservation) }}">{{ $reservation->reference_number }}</a></td><td>{{ $label($reservation->event_name) }}</td><td>{{ $reservation->event_type ?: 'Not specified' }}</td><td class="whitespace-nowrap">{{ $reservation->reservation_date->format('M d, Y') }}</td><td class="whitespace-nowrap">{{ \Carbon\CarbonImmutable::parse($reservation->start_time)->format('g:i A') }} – {{ \Carbon\CarbonImmutable::parse($reservation->end_time)->format('g:i A') }}</td><td><x-operational-status :status="$reservation->status" /></td></tr>
                    @empty<tr><td colspan="6" class="bi-empty">No reservations match these filters. Try another year, month, status, or event type.</td></tr>@endforelse</tbody>
                </table>
            </div>
            <p class="bi-table-footer">Showing {{ $overviewReservations->count() }} of {{ number_format($totalReservations) }} reservations</p>
        </section>
        <section class="card bi-status-panel" aria-labelledby="status-title">
            <header class="bi-panel-header"><div><h2 id="status-title">Reservation Status Overview</h2><p>Distribution of reservations by status.</p></div></header>
            <div class="bi-donut" role="img" aria-label="{{ $totalReservations ? 'Status distribution for '.$totalReservations.' reservations. Counts and percentages are listed below.' : 'No matching reservations. No status distribution available.' }}">
                <svg viewBox="0 0 120 120" aria-hidden="true">
                    <circle cx="60" cy="60" r="45" fill="none" stroke="#e2e8f0" stroke-width="14" />
                    @foreach ($segments as $segment)
                        @php $length = $segment['count'] / $totalReservations * 100; @endphp
                        <circle cx="60" cy="60" r="45" fill="none" stroke="{{ $statusColors[$segment['status']] ?? '#64748b' }}" stroke-width="14" pathLength="100" stroke-dasharray="{{ $length }} {{ 100 - $length }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 60 60)" />
                        @php $offset += $length; @endphp
                    @endforeach
                </svg>
                <div class="bi-donut-center"><strong>{{ number_format($totalReservations) }}</strong><span>Total Reservations</span></div>
            </div>
            @if (! $totalReservations)<p class="bi-empty !py-2">No status data for these filters.</p>@endif
            <ul class="bi-legend" aria-label="Status counts and percentages">
                @foreach ($statusBreakdown as $row)<li><span class="bi-legend-label"><span class="bi-dot" style="background-color: {{ $statusColors[$row['status']] ?? '#64748b' }}" aria-hidden="true"></span>{{ $label($row['status']) }}</span><strong>{{ number_format($row['count']) }}</strong><span>{{ $row['percentage'] === null ? '—' : $row['percentage'].'%' }}</span></li>@endforeach
            </ul>
        </section>
    </div>
    <footer class="bi-notes"><p>Utilization counts approved event hours within configured operating hours, with overlapping bookings merged per facility and date. Calendar blackouts are excluded from booked and bookable hours. Setup and cleanup are excluded under the current conflict rules.</p><a class="bi-link" href="{{ route('admin.dashboard', $filters + ['export' => 'csv']) }}">Export filtered analytics (CSV)</a></footer>
</div>
@endsection
