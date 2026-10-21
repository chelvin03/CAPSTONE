<div class="admin-analysis-grid">
    <section class="reference-panel admin-panel"><header><div><h2>1. Monthly Reservation Trend — {{ $selectedYear === 'all' ? 'All years' : $selectedYear }}</h2><p>Number of reservations per month.</p></div></header>
        @include('dashboard.partials.reference-chart', ['rows' => $monthly, 'chartWidth' => 660, 'chartLabel' => 'Monthly Reservation Trend, number of reservations by scheduled event month'])
        <p class="analysis-axis">Number of Reservations · selected event-date period</p>
    </section>
    <section class="reference-panel admin-panel"><header><div><h2>2. Required-field Completeness Overview</h2><p>Required information only; optional event type is excluded. See Event-type Coverage above.</p></div></header>
        <div class="analysis-completeness"><div class="reference-donut"><svg viewBox="0 0 120 120" role="img" aria-label="{{ $completeCount }} complete, {{ $incompleteCount }} incomplete reservations"><circle cx="60" cy="60" r="45" fill="none" stroke="#e2e8f0" stroke-width="19"/>
            @if($totalReservations)<circle cx="60" cy="60" r="45" fill="none" stroke="#0860ff" stroke-width="19" pathLength="100" stroke-dasharray="{{ $completenessRate }} {{ 100-$completenessRate }}" transform="rotate(-90 60 60)"/><circle cx="60" cy="60" r="45" fill="none" stroke="#ff3f45" stroke-width="19" pathLength="100" stroke-dasharray="{{ 100-$completenessRate }} {{ $completenessRate }}" stroke-dashoffset="{{ -$completenessRate }}" transform="rotate(-90 60 60)"/>@endif
            </svg><div><span>Total</span><strong>{{ $totalReservations }}</strong></div></div>
            <ul class="analysis-complete-legend"><li><i style="background:#0860ff"></i><span>Complete</span><strong>{{ $completeCount }}</strong><small>{{ $completenessRate }}%</small></li><li><i style="background:#ff3f45"></i><span>Incomplete</span><strong>{{ $incompleteCount }}</strong><small>{{ $totalReservations ? round(100-$completenessRate,1) : 0 }}%</small></li></ul>
        </div>
    </section>
    <section class="reference-panel admin-panel"><header><div><h2>3. Most Common Event Types (Top 10)</h2><p>Number of reservations by event type.</p></div></header>
        <?php $topEvents = $eventDemand->take(10); $eventMaximum = max(1, $topEvents->max('count')); ?>
        <div class="analysis-events">@forelse($topEvents as $row)<div class="analysis-event-row"><span title="{{ $row['label'] }}">{{ $row['label'] }}</span><div><i style="width:{{ $row['count']/$eventMaximum*92 }}%"></i><strong>{{ $row['count'] }}</strong></div></div>@empty<p>No matching reservations.</p>@endforelse</div><p class="analysis-axis">Number of Reservations</p>
        <details class="admin-chart-details"><summary>All event types and percentages</summary><ul>@foreach($eventDemand as $row)<li>{{ $row['label'] }}: {{ $row['count'] }} ({{ $row['percentage'] }}%)</li>@endforeach</ul></details>
    </section>
    <section class="reference-panel admin-panel"><header><div><h2>4. Peak Reservation Hours</h2><p>Number of reservations by day and time. {{ isset($filters['status']) ? $label($filters['status']) : (($filters['heat_scope'] ?? '') === 'all' ? 'All statuses' : 'Approved bookings') }}.</p></div></header>
        <div class="analysis-heat-wrap"><table class="analysis-week-heat"><tbody>@foreach($heat as $slot)<tr><th scope="row">{{ $slot['label'] }}</th>@foreach($slot['counts'] as $index => $count)<td style="background:rgba(0,92,246,{{ .09 + .91*$count/$heatMax }})" title="{{ $weekdays[$index] }} {{ $slot['label'] }}: {{ $count }} reservations"><span class="sr-only">{{ $count }}</span></td>@endforeach</tr>@endforeach</tbody><tfoot><tr><th></th>@foreach($weekdays as $day)<th scope="col">{{ substr($day,0,3) }}</th>@endforeach</tr></tfoot></table><div class="analysis-heat-scale"><span>High</span><i></i><span>Low</span></div></div>
        <details class="admin-chart-details"><summary>Peak day, time and month</summary><p>Peak Day: {{ $heatPeakDay }} · Peak Time: {{ $peakTime }} · Peak Month: {{ $heatPeakMonth }}.</p><p>{{ $heatUsesOperatingHours ? 'Configured operating hours' : '6 AM–10 PM fallback; operating hours unavailable' }}. Hover a cell for exact counts. Ties use earliest interval.</p></details>
    </section>
    <section id="cancellation-chart" class="reference-panel admin-panel analysis-cancellation"><header><div><h2>5. Cancellation Rate Trend — {{ $selectedYear === 'all' ? 'All years' : $selectedYear }}</h2><p>Monthly cancellation percentage for the selected period.</p></div>@include('dashboard.partials.chart-year-filter', ['yearFilterId' => 'cancellation-year', 'yearFilterLabel' => 'Cancellation analysis year'])</header>
        @include('dashboard.partials.cancellation-timeline')
    </section>

</div>
