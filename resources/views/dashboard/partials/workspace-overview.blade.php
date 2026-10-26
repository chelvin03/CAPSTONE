<section class="bi-section" aria-labelledby="overview-heading">
    <h2 id="overview-heading">Reservation Overview</h2>
    <div class="bi-overview">
        @foreach([
            ['Total Reservations', $totalReservations, 'bi-calendar2-check', '#eef6ff', '#0864ff', 'All reservation requests'],
            ['Approved', $approvedReservations, 'bi-check-circle-fill', '#eafaf3', '#00a76b', 'Currently approved'],
            ['Pending Review', $pendingReservations, 'bi-clock', '#fff8e7', '#ffa800', 'Awaiting administrator review'],
            ['Waiting List', $statusBreakdown->firstWhere('status', 'waiting_list')['count'] ?? 0, 'bi-hourglass-split', '#fff4e8', '#e78a00', 'Waiting for an available schedule'],
            ['Cancelled', $cancelledReservations, 'bi-x-circle-fill', '#fff0f2', '#ff334e', 'Cancelled reservations'],
            ['Rejected', $statusBreakdown->firstWhere('status', 'rejected')['count'] ?? 0, 'bi-chat-square-text-fill', '#f4efff', '#7d36f4', 'Rejected reservations']
        ] as [$overviewTitle, $overviewCount, $overviewIcon, $overviewBg, $overviewInk, $overviewDescription])
        <article class="bi-stat" style="--stat-bg:{{ $overviewBg }};--stat-ink:{{ $overviewInk }}"><i class="bi {{ $overviewIcon }}" aria-hidden="true"></i><div><h3>{{ $overviewTitle }}</h3><strong>{{ number_format($overviewCount) }}</strong><small>{{ $overviewTitle === 'Total Reservations' ? $overviewDescription : ($totalReservations ? round($overviewCount / $totalReservations * 100, 1).'% of total' : 'No data available') }}</small></div></article>
        @endforeach
    </div>
    @if(!$totalReservations)<p class="decision-note" role="status">No reservations match these filters.</p>@endif
    @if($availableHours <= 0)<p class="decision-note" role="status">No bookable hours in this period. Utilization cannot be calculated.</p>@endif
</section>
<section class="bi-section" id="bi-kpis"><h2>Key Performance Indicators</h2>@include('dashboard.partials.kpi-cards')</section>
<section class="bi-section"><h2>Reservation Trends</h2><div class="bi-two-column">
    @include('dashboard.partials.workspace-charts')
    <section class="reference-panel"><header><h2>Reservation Status Overview</h2><p>All matching statuses, including completed, waiting-list and no-show records.</p></header>
        <div class="bi-status-body"><div class="reference-donut"><svg viewBox="0 0 120 120" role="img" aria-label="Reservation status distribution: {{ $totalReservations }} total">
            <circle cx="60" cy="60" r="45" fill="none" stroke="#eaf0fa" stroke-width="20"/>
            @php($statusOffset = 0)
            @foreach($statusBreakdown->where('count', '>', 0) as $statusSegment)
                @php($statusPercent = $totalReservations ? $statusSegment['count'] / $totalReservations * 100 : 0)
                <circle cx="60" cy="60" r="45" fill="none" stroke="{{ \App\Services\DashboardAnalyticsService::STATUS_COLORS[$statusSegment['status']] ?? '#64748b' }}" stroke-width="20" pathLength="100" stroke-dasharray="{{ $statusPercent }} {{ 100 - $statusPercent }}" stroke-dashoffset="{{ -$statusOffset }}" transform="rotate(-90 60 60)"><title>{{ $label($statusSegment['status']) }}: {{ $statusSegment['count'] }}</title></circle>
                @php($statusOffset += $statusPercent)
            @endforeach
        </svg><div><span>Total</span><strong>{{ $totalReservations }}</strong></div></div>
        <ul>@foreach($statusBreakdown->where('count', '>', 0) as $statusSegment)<li><i style="background:{{ \App\Services\DashboardAnalyticsService::STATUS_COLORS[$statusSegment['status']] ?? '#64748b' }}"></i><span>{{ $label($statusSegment['status']) }}</span><strong>{{ $statusSegment['count'] }}</strong><small>{{ $totalReservations ? round($statusSegment['count'] / $totalReservations * 100, 1) : 0 }}%</small></li>@endforeach</ul></div>
    </section>
</div></section>
<section class="bi-section"><h2>Facility Utilization</h2><div class="bi-two-column bi-facility-layout">
    <section class="reference-panel"><header><h2>Gym Utilization by Month</h2><p>Scheduled occupied hours / bookable operating hours. Blocks excluded; scheduled use is not actual attendance.</p></header>
        <div class="bi-util-chart"><svg class="admin-chart" viewBox="0 0 660 220" role="img" aria-label="Monthly scheduled utilization percentage">
            @foreach(range(0, 5) as $tick)<line x1="45" x2="640" y1="{{ 180 - $tick * 30 }}" y2="{{ 180 - $tick * 30 }}" class="chart-grid"/><text x="37" y="{{ 184 - $tick * 30 }}" text-anchor="end" class="chart-axis">{{ $tick * 20 }}%</text>@endforeach
            @php($utilStep = 590 / max(1, count($performance['months'])))
            @foreach($performance['months'] as $utilIndex => $utilMonth)
                @php($utilX = 50 + ($utilIndex + .5) * $utilStep)
                <rect x="{{ $utilX - 11 }}" y="{{ 180 - ($utilMonth['utilizationRate'] ?? 0) * 1.5 }}" width="22" height="{{ ($utilMonth['utilizationRate'] ?? 0) * 1.5 }}" rx="2" fill="#0864ff"><title>{{ $utilMonth['label'] }}: {{ $utilMonth['utilizationRate'] === null ? 'N/A' : $utilMonth['utilizationRate'].'%' }}</title></rect>
                <text x="{{ $utilX }}" y="{{ 172 - ($utilMonth['utilizationRate'] ?? 0) * 1.5 }}" text-anchor="middle" class="chart-axis">{{ $utilMonth['utilizationRate'] === null ? 'N/A' : $utilMonth['utilizationRate'].'%' }}</text><text x="{{ $utilX }}" y="204" text-anchor="middle" class="chart-axis">{{ explode(' ', $utilMonth['label'])[0] }}</text>
            @endforeach
        </svg></div>
        @if($performance['trendLimited'])<p class="analysis-axis">Showing the last 12 months of the selected range.</p>@endif
    </section>
    @include('dashboard.partials.workspace-heat')
</div></section>
<section class="bi-section"><h2>Cancellation Analysis</h2>@include('dashboard.partials.workspace-cancellation')</section>
<section class="bi-section"><h2>Event Outcomes and Attendance</h2>@include('dashboard.partials.event-outcomes')</section>
<section class="bi-section"><h2>Insights and Recommendations</h2><div class="bi-recommendations">
    <article><h3><i class="bi bi-lightbulb text-amber-500" aria-hidden="true"></i> Key Insights</h3><ul>@foreach($decisionCards as $insightCard)<li>{{ $insightCard['finding'] }}</li>@endforeach<li>{{ $eventResults['unknown']->count() }} past events still need a verified outcome.</li></ul></article>
    <article><h3><i class="bi bi-check-circle-fill text-green-600" aria-hidden="true"></i> Recommended Actions</h3><ul>@foreach($decisionCards as $insightCard)<li>{{ $insightCard['action'] }}</li>@endforeach<li>Record verified outcomes and actual attendance after events end.</li></ul></article>
</div><details class="bi-secondary"><summary>Insights and suggested actions — evidence and interpretation</summary>@include('dashboard.partials.decision-support')</details></section>
<section class="bi-section"><h2>Administrative Tools</h2><div class="bi-tool-grid">
    @foreach([
        ['#kpi-target-settings', 'bi-gear', 'Configure KPI Targets', 'Set agreed performance goals'],
        [route('admin.reservations.index', \Illuminate\Support\Arr::except($filters, ['year', 'grouping', 'heat_scope'])), 'bi-file-earmark-text', 'View Supporting Records', 'Inspect matching reservations'],
        [route('admin.dashboard', $filters + ['export' => 'csv']), 'bi-download', 'Export Reports', 'Download the BI report as CSV'],
        ['#bi-kpis', 'bi-info-circle', 'Definitions and Formulas', 'Review metrics and evidence'],
        ['#dashboard-advanced-filters', 'bi-funnel', 'Advanced Filters', 'Filter status and event type']
    ] as [$toolUrl, $toolIcon, $toolTitle, $toolDescription])<a href="{{ $toolUrl }}" @if(str_starts_with($toolUrl, '#')) onclick="const target = document.getElementById(this.hash.slice(1)); if (target) { let parent = target; while(parent) { if(parent.tagName === 'DETAILS') parent.open = true; parent = parent.parentElement; } target.scrollIntoView({behavior:'smooth',block:'start'}); }" @endif><i class="bi {{ $toolIcon }}" aria-hidden="true"></i><span><strong>{{ $toolTitle }}</strong><small>{{ $toolDescription }}</small></span></a>@endforeach
</div></section>
