<div class="analytics-grid facility-grid">
    <section class="analytics-panel"><header><h2>Scheduled Utilization Trend</h2>@include('admin.analytics.control', ['controlName' => 'trend', 'controlLabel' => 'Utilization trend grouping', 'choices' => ['monthly' => 'Monthly', 'quarterly' => 'Quarterly']])</header>
        @include('admin.analytics.utilization-trend', ['rows' => $facilityWorkspace['months']])
    </section>
    <section class="analytics-panel"><header><h2>Utilization by Weekday</h2>@include('admin.analytics.control', ['controlName' => 'weekday_metric', 'controlLabel' => 'Weekday utilization metric', 'choices' => ['hours' => 'Booked Hours', 'percentage' => 'Share of Hours (%)']])</header>
        @include('admin.analytics.chart', ['rows' => $facilityWorkspace['weekdays'], 'series' => [['count', ($facilityWorkspace['options']['weekday_metric'] ?? 'hours') === 'hours' ? 'Booked Hours' : 'Share of Hours (%)', '#287cf0']], 'kind' => 'bar', 'chartTitle' => 'Utilization by weekday', 'hideChartDetails' => true, 'showBarValues' => true])
    </section>
    <section class="analytics-panel"><header><h2>Hourly Occupancy</h2>@include('admin.analytics.control', ['controlName' => 'occupancy', 'controlLabel' => 'Occupancy grouping', 'choices' => ['hour' => 'By Hour', 'period' => 'By Period']])</header>
        @include('admin.analytics.heat', ['heat' => $facilityWorkspace['heat'], 'heatMax' => max(1, ...array_merge([0], ...array_column($facilityWorkspace['heat'], 'counts'))), 'heatMetric' => 'hours', 'hideHeatNote' => true])
    </section>
    <section class="analytics-panel"><header><h2>Reservation Outcomes</h2>@include('admin.analytics.control', ['controlName' => 'outcomes', 'controlLabel' => 'Reservation outcome scope', 'choices' => ['all' => 'All Events', 'ended' => 'Ended Events']])</header>
        @include('admin.analytics.donut', ['rows' => $facilityWorkspace['outcomeRows']->values(), 'chartTitle' => 'Reservation outcomes', 'donutCaption' => 'Total Bookings', 'showEmptySegments' => true])
    </section>
    <section class="analytics-panel analytics-wide"><header><h2>Utilization Notes</h2>@include('admin.analytics.control', ['controlName' => 'notes_order', 'controlLabel' => 'Utilization note order', 'choices' => ['latest' => 'Latest First', 'oldest' => 'Oldest First']])</header>
        <div class="analytics-table"><table><thead><tr><th>Date</th><th>Note</th><th>Type</th><th>Related Value</th></tr></thead><tbody>@foreach($facilityWorkspace['notes'] as $note)<tr><td>{{ \Carbon\CarbonImmutable::parse($note['date'])->format('M j, Y') }}</td><td>{{ $note['note'] }}</td><td><span class="facility-note-type facility-note-{{ strtolower($note['type']) }}">{{ $note['type'] }}</span></td><td>{{ $note['value'] }}</td></tr>@endforeach</tbody></table></div>
    </section>
</div>
