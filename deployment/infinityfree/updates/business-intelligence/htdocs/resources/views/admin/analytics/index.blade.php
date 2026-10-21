@extends('layouts.app')
@section('title', 'Business Intelligence Analytics')
@section('content')
<link rel="stylesheet" href="{{ asset('css/business-intelligence.css') }}?v={{ filemtime(public_path('css/business-intelligence.css')) }}">
<div class="bi-page">
    <header class="bi-heading"><div><p class="bi-eyebrow">MCST GYMNASIUM / ANALYTICS</p><h1>Business Intelligence</h1><p>Understand reservation demand and plan gym operations.</p></div><a class="bi-button" href="{{ route('admin.dashboard', $filters + ['export' => 'csv']) }}">Download CSV</a></header>
    <form method="GET" action="{{ route('admin.analytics.index') }}" class="bi-filters">
        <label>From<input type="date" name="date_from" value="{{ $filters['date_from'] }}" required></label>
        <label>To<input type="date" name="date_to" value="{{ $filters['date_to'] }}" required></label>
        <label>Group by<select name="grouping">@foreach(['daily','weekly','monthly','quarterly','annual'] as $group)<option value="{{ $group }}" @selected($filters['grouping'] === $group)>{{ ucfirst($group) }}</option>@endforeach</select></label>
        <label>Event type<select name="category"><option value="">All event types</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ \App\Services\DashboardAnalyticsService::label($category) }}</option>@endforeach</select></label>
        <button class="bi-button">Apply filters</button><a href="{{ route('admin.analytics.index') }}">Reset</a>
    </form>
    <p class="bi-period">Scheduled dates: {{ $filters['date_from'] }} to {{ $filters['date_to'] }} · Updated {{ $generatedAt->format('M j, Y g:i A') }}</p>
    <div class="bi-metrics">
        @foreach([
            ['Total requests', number_format($totalReservations), $previousTotal ? ($change >= 0 ? '+' : '').$change.'% vs previous period' : 'No previous-period baseline'],
            ['Pending review', number_format($pendingReservations), $oldPending.' pending for at least 7 days'],
            ['Scheduled utilization', $utilizationRate === null ? 'N/A' : $utilizationRate.'%', 'Approved hours / bookable operating hours'],
            ['Cancellation rate', $cancellationRate === null ? 'N/A' : $cancellationRate.'%', $cancelledReservations.' cancelled requests'],
        ] as [$caption,$value,$detail])<article class="bi-metric"><p>{{ $caption }}</p><strong>{{ $value }}</strong><small>{{ $detail }}</small></article>@endforeach
    </div>
    @if(!$totalReservations)<div class="bi-empty" role="status">No reservations match this period. Try a wider date range.</div>@endif
    <div class="bi-grid">
        <section class="bi-panel bi-wide"><h2>Reservation demand over time</h2><p>All matching requests, grouped by scheduled date.</p><div class="bi-trend" role="img" aria-label="Reservation demand by period; exact counts are listed below">
            @foreach($trendActivity as $row)<div class="bi-column" title="{{ $row['from'] }} to {{ $row['to'] }}: {{ $row['count'] }} requests"><span>{{ $row['count'] }}</span><i style="height: {{ $row['count'] / $trendMaximum * 130 }}px"></i><small>{{ $row['label'] }} {{ $row['year'] }}</small></div>@endforeach
        </div><details><summary>View exact counts</summary><div class="bi-table-wrap"><table><thead><tr><th>From</th><th>To</th><th>Requests</th></tr></thead><tbody>@foreach($trendActivity as $row)<tr><td>{{ $row['from'] }}</td><td>{{ $row['to'] }}</td><td>{{ $row['count'] }}</td></tr>@endforeach</tbody></table></div></details></section>
        @foreach(['Reservation outcomes' => $statusBreakdown->map(fn($r) => ['label' => \App\Services\DashboardAnalyticsService::label($r['status']), 'count' => $r['count']]), 'Event demand' => $eventDemand, 'Demand by weekday' => $weekdayDemand, 'Requestor types' => $requestorDemand] as $heading => $rows)
        <section class="bi-panel"><h2>{{ $heading }}</h2><p>Number of matching reservation requests.</p>@forelse($rows as $row)<div class="bi-bar-row"><div><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div><div class="bi-track"><i style="width: {{ $totalReservations ? $row['count'] / $totalReservations * 100 : 0 }}%"></i></div></div>@empty<p>No matching data.</p>@endforelse</section>
        @endforeach
        <section class="bi-panel bi-wide"><h2>Operational insights</h2><div class="bi-insights">
            <article><h3>Busiest weekday</h3><strong>{{ $peakWeekday['label'] }}</strong><p>{{ $peakWeekday['count'] }} requests. Plan support around the most requested days.</p></article>
            <article><h3>Peak booking window</h3><strong>{{ $peakTimeSlot['label'] }}</strong><p>{{ $peakTimeSlot['count'] }} overlapping requests. A request may span multiple time windows.</p></article>
            <article><h3>Review workload</h3><strong>{{ $oldPending }} overdue requests</strong><p>New or validated requests submitted at least 7 days ago. <a href="{{ route('admin.reservations.index') }}">Review reservations</a></p></article>
            <article><h3>Average decision time</h3><strong>{{ $processingHours === null ? 'N/A' : $processingHours.' hours' }}</strong><p>Based on {{ $processingCount }} requests with recorded decision timestamps.</p></article>
        </div></section>
    </div>
    <p class="bi-footnote">Utilization measures approved scheduled hours, with overlaps merged and schedule blocks excluded. It is not actual attendance or measured gym usage. Expected attendees for approved and completed events: {{ number_format($expectedAttendees) }}. Charts reflect the selected scheduled dates, including future bookings.</p>
</div>
@endsection
