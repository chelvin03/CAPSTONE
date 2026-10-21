    <section class="reference-panel admin-panel"><header><div><h2>Required-field Completeness Overview</h2><p>Required information only; optional event type is excluded. See Event-type Coverage above.</p></div></header>
        <div class="analysis-completeness"><div class="reference-donut"><svg viewBox="0 0 120 120" role="img" aria-label="{{ $completeCount }} complete, {{ $incompleteCount }} incomplete reservations"><circle cx="60" cy="60" r="45" fill="none" stroke="#e2e8f0" stroke-width="19"/>
            @if($totalReservations)<circle cx="60" cy="60" r="45" fill="none" stroke="#0860ff" stroke-width="19" pathLength="100" stroke-dasharray="{{ $completenessRate }} {{ 100-$completenessRate }}" transform="rotate(-90 60 60)"/><circle cx="60" cy="60" r="45" fill="none" stroke="#ff3f45" stroke-width="19" pathLength="100" stroke-dasharray="{{ 100-$completenessRate }} {{ $completenessRate }}" stroke-dashoffset="{{ -$completenessRate }}" transform="rotate(-90 60 60)"/>@endif
            </svg><div><span>Total</span><strong>{{ $totalReservations }}</strong></div></div>
            <ul class="analysis-complete-legend"><li><i style="background:#0860ff"></i><span>Complete</span><strong>{{ $completeCount }}</strong><small>{{ $completenessRate }}%</small></li><li><i style="background:#ff3f45"></i><span>Incomplete</span><strong>{{ $incompleteCount }}</strong><small>{{ $totalReservations ? round(100-$completenessRate,1) : 0 }}%</small></li></ul>
        </div>
    </section>
    <section class="reference-panel admin-panel"><header><div><h2>Most Common Event Types (Top 10)</h2><p>Number of reservations by event type.</p></div></header>
        <?php $topEvents = $eventDemand->take(10); $eventMaximum = max(1, $topEvents->max('count')); ?>
        <div class="analysis-events">@forelse($topEvents as $row)<div class="analysis-event-row"><span title="{{ $row['label'] }}">{{ $row['label'] }}</span><div><i style="width:{{ $row['count']/$eventMaximum*92 }}%"></i><strong>{{ $row['count'] }}</strong></div></div>@empty<p>No matching reservations.</p>@endforelse</div><p class="analysis-axis">Number of Reservations</p>
        <details class="admin-chart-details"><summary>All event types and percentages</summary><ul>@foreach($eventDemand as $row)<li>{{ $row['label'] }}: {{ $row['count'] }} ({{ $row['percentage'] }}%)</li>@endforeach</ul></details>
    </section>
