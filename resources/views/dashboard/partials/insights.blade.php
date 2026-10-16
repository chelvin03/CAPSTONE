@php($eventPalette = ['#1859f6','#ef4444','#10b981','#f5b400','#8b35f6','#64748b','#06b6d4','#ec4899'])
<div class="admin-charts-top">
    <section class="reference-panel admin-panel"><header><h2>Monthly Reservation Trend — {{ $selectedYear === 'all' ? 'All years' : $selectedYear }}</h2></header>
        @include('dashboard.partials.reference-chart', ['rows' => $monthly, 'chartLabel' => 'Monthly reservation counts by scheduled event date'])
        <details class="admin-chart-details"><summary>Period and counts</summary><p>Scheduled event dates; selected period and filters. Zero months are included. Month/day filters restrict this chart. A full-year comparison is available under Additional analytics.</p><div class="admin-data-list">@foreach($monthly as $row)<span>{{ $row['label'] }}: {{ $row['count'] }}</span>@endforeach</div></details>
    </section>
    <section class="reference-panel admin-panel"><header><h2>Forecasted Reservations — Next 3 Months</h2><i class="bi bi-info-circle" title="Three completed month moving average. Estimates only; not confirmed bookings." aria-hidden="true"></i></header>
        @if($estimate !== null)
            @include('dashboard.partials.reference-chart', ['rows' => $forecast, 'chartLabel' => 'Estimated reservation demand for the next three months', 'isForecast' => true])
            <p class="admin-chart-note">Based on the average of the last 3 completed months ({{ $history[0]['label'] }} – {{ $history[2]['label'] }}). This is an estimate only.</p>
        @else<div class="admin-no-forecast"><i class="bi bi-bar-chart" aria-hidden="true"></i><p>Insufficient history</p><span>Three completed months are required to estimate demand.</span></div>@endif
        <details class="admin-chart-details"><summary>Historical months used</summary><p>Analysis cutoff: {{ $cutoff->format('M j, Y') }}. Current incomplete month and future schedules excluded. Limited baseline confidence; seasonality is not modeled.</p>@foreach($history as $row)<p>{{ $row['label'] }}: {{ $row['count'] }} reservations</p>@endforeach</details>
    </section>
    <section class="reference-panel admin-panel"><header><h2>Peak Time of Reservations</h2><i class="bi bi-info-circle" title="Reservations overlapping each time interval by month. Approved by default; change demand scope under More filters." aria-hidden="true"></i></header>
        <div class="admin-heat-scroll" tabindex="0" role="region" aria-label="Peak reservation time by month"><table class="admin-heat"><tbody>
        @foreach($heat as $slot)<tr><th scope="row">{{ $slot['label'] }}</th>@foreach($slot['month_counts'] as $index => $count)<td style="background:rgba(24,89,246,{{ .08 + .92 * $count / $monthHeatMax }})" title="{{ $monthly[$index]['label'] }} · {{ $slot['label'] }}: {{ $count }} reservations"><span class="sr-only">{{ $count }}</span></td>@endforeach</tr>@endforeach
        </tbody><tfoot><tr><th></th>@foreach($monthly as $row)<th scope="col">{{ $row['label'] }}</th>@endforeach</tr></tfoot></table></div>
        <div class="admin-heat-key"><span>Low</span>@foreach(range(1,6) as $shade)<i style="background:rgba(24,89,246,{{ $shade / 6 }})"></i>@endforeach<span>High</span></div>
        <details class="admin-chart-details"><summary>Peak days and weekday breakdown</summary><p>{{ isset($filters['status']) ? $label($filters['status']) : (($filters['heat_scope'] ?? '') === 'all' ? 'All statuses' : 'Approved reservations') }}. {{ $heatUsesOperatingHours ? 'Configured operating hours' : '6 AM–10 PM fallback; operating hours unavailable' }}.</p><p>Peak Day: <strong>{{ $heatPeakDay }}</strong> · Peak Time: <strong>{{ $peakTime }}</strong> · Peak Month: <strong>{{ $heatPeakMonth }}</strong>. Ties use the earliest interval.</p>
            <div class="bi-scroll"><table class="bi-table"><thead><tr><th>Time</th>@foreach($weekdays as $day)<th>{{ substr($day,0,3) }}</th>@endforeach</tr></thead><tbody>@foreach($heat as $slot)<tr><th>{{ $slot['label'] }}</th>@foreach($slot['counts'] as $count)<td>{{ $count }}</td>@endforeach</tr>@endforeach</tbody></table></div>
        </details>
    </section>
</div>
<div class="admin-charts-bottom">
    <section class="reference-panel admin-panel"><header><h2>Most Common Event Types</h2></header>
        @php($eventOffset = 0)
        <div class="admin-event-body"><div class="reference-donut"><svg viewBox="0 0 120 120" role="img" aria-label="Event type shares, with counts and percentages alongside"><circle cx="60" cy="60" r="45" fill="none" stroke="#e2e8f0" stroke-width="17"/>
            @foreach($eventDemand as $index => $row)
                @php($share = $totalReservations ? $row['count'] / $totalReservations * 100 : 0)
                <circle cx="60" cy="60" r="45" fill="none" stroke="{{ $eventPalette[$index % count($eventPalette)] }}" stroke-width="17" pathLength="100" stroke-dasharray="{{ $share }} {{ 100-$share }}" stroke-dashoffset="{{ -$eventOffset }}" transform="rotate(-90 60 60)"/>
                @php($eventOffset += $share)
            @endforeach
            </svg><div><span>Total Reservations</span><strong>{{ number_format($totalReservations) }}</strong></div></div>
            <ul class="admin-event-legend">@forelse($eventDemand as $index => $row)<li><i style="background:{{ $eventPalette[$index % count($eventPalette)] }}" aria-hidden="true"></i><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong><small>{{ $row['percentage'] }}%</small></li>@empty<li>No matching reservations.</li>@endforelse</ul>
        </div><details class="admin-chart-details"><summary>Event insights</summary><p>Most requested: {{ $mostCommonEventType }}. Categories come from recorded reservation data.</p></details>
    </section>
    <section class="reference-panel admin-panel"><header><h2>Cancellation Rate Trend</h2></header>
        <div class="admin-chart-legend"><span><i class="legend-total"></i>Total Reservations</span><span><i class="legend-cancelled"></i>Cancellation Rate</span></div>
        @include('dashboard.partials.reference-chart', ['rows' => $monthly, 'chartLabel' => 'Monthly reservation totals and cancellation percentage', 'withRates' => true])
        <details class="admin-chart-details"><summary>Monthly counts and rates · {{ number_format($cancellationRate ?? 0,1) }}% overall</summary><p>{{ $cancelledReservations }} cancellations. Highest rate: {{ $highestCancellationMonth ? $highestCancellationMonth['label'].' ('.$highestCancellationMonth['rate'].'%)' : 'No cancellations' }}. Ties use the earliest month.</p><table class="bi-table"><thead><tr><th>Month</th><th>Total</th><th>Cancelled</th><th>Rate</th></tr></thead><tbody>@foreach($monthly as $row)<tr><th>{{ $row['label'] }}</th><td>{{ $row['count'] }}</td><td>{{ $row['cancelled'] }}</td><td>{{ number_format($row['rate'],1) }}%</td></tr>@endforeach</tbody></table></details>
    </section>
    <section class="reference-panel admin-panel admin-attention"><header><h2><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Reservations Requiring Attention</h2><a href="{{ route('admin.reservations.index') }}" class="admin-view-all">View All</a></header>
        <div class="admin-issues">@forelse($issues as $description => $affected)<details class="admin-issue"><summary><i class="bi {{ str_contains($description,'letter') ? 'bi-file-earmark-text' : (str_contains($description,'time') ? 'bi-clock' : 'bi-people') }}" aria-hidden="true"></i><span>{{ $description }}</span><strong>{{ count($affected) }}</strong></summary><div class="admin-issue-records"><p>View Details</p>@foreach($affected as $record)<a href="{{ route('admin.reservations.show', $record['id']) }}">{{ $record['reference'] }}</a>@endforeach</div></details>@empty<p class="admin-chart-note">No issues found for the selected filters.</p>@endforelse</div>
        <details class="admin-chart-details"><summary>Review rules</summary><p>Manual review only; no approval statuses are changed. Event type, setup and cleanup are optional. Missing event type does not reduce completeness. Public requests require a permit and contact email; administrator entries do not.</p></details>
    </section>
</div>
<details class="admin-more-insights"><summary>Peak months &amp; data completeness</summary>
<div class="bi-grid bi-bottom">
    <section class="reference-panel"><header><h2>Peak Reservation Months</h2><p>Highest demand: {{ $peaks->first()['label'] ?? 'No matching reservations' }}</p></header><div class="bi-body">@forelse($peaks as $row)<p>{{ $row['label'] }}: <strong>{{ $row['count'] }}</strong> ({{ $row['percentage'] }}%)</p><progress max="{{ max(1,$totalReservations) }}" value="{{ $row['count'] }}">{{ $row['percentage'] }}%</progress>@empty<p>No scheduled reservations.</p>@endforelse</div></section>
    <section class="reference-panel"><header><h2>Data Completeness Overview</h2><p>Required workflow fields only; attendees between 1 and 2,000.</p></header><div class="bi-body"><strong>{{ $completenessRate }}% complete</strong><progress max="100" value="{{ $completenessRate }}">{{ $completenessRate }}%</progress><p>Complete reservations: {{ $completeCount }}</p><p>Incomplete reservations: {{ $incompleteCount }}</p>@if(!$totalReservations)<p>No matching reservations; completeness is displayed as 0%.</p>@endif</div></section>
</div></details>
