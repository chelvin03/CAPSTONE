<section class="kpi-section" aria-labelledby="kpi-heading">
    <header class="kpi-heading"><div><p class="decision-eyebrow">KEY PERFORMANCE INDICATORS</p><h2 id="kpi-heading">Performance against configured targets</h2></div><a href="#kpi-target-settings">Configure targets</a></header>
    @if(session('success'))<p class="decision-note" role="status">{{ session('success') }}</p>@endif
    <p class="kpi-scope">{{ $kpiScopeNote }}</p>
    <p class="kpi-scope">{{ $kpiComparisonNote }}</p>
    @if($kpiSampleNote)<p class="decision-note">{{ $kpiSampleNote }}</p>@endif
    <div class="kpi-grid">
        @foreach($kpiCards as $card)
        <article class="kpi-card">
            <h3>{{ $card['title'] }}</h3>
            <p class="kpi-value">{{ $card['value'] === null ? 'N/A' : number_format($card['value'], $card['unit'] === 'requests' ? 0 : 1).($card['unit'] === '%' ? '%' : '') }} @if($card['value'] !== null && $card['unit'] === 'hours')<small>hours</small>@endif</p>
            <p class="kpi-badge {{ $card['met'] === null ? 'neutral' : ($card['met'] ? 'met' : 'attention') }}">{{ $card['target'] === null ? 'Target not configured' : ($card['met'] === null ? 'No valid data' : ($card['met'] ? 'Target met' : 'Needs attention')) }}</p>
            @if($card['target'] !== null)<p class="kpi-definition">Target: {{ $card['direction'] === 'min' ? 'at least' : 'at most' }} {{ $card['target'] }} {{ $card['unit'] }}.</p>@endif
            <p class="kpi-comparison">{{ $card['comparison'] }}</p>
            <details><summary>Definition and supporting records ({{ $card['evidence']->count() }})</summary><p class="kpi-definition">{{ $card['definition'] }}</p>
                @if($card['key'] === 'review_hours' && $card['evidence']->count() < 5)<p class="kpi-definition">Fewer than 5 valid decisions; interpret this average cautiously.</p>@endif
                @include('dashboard.partials.performance-records', ['evidenceRows' => $card['evidence']])
            </details>
        </article>
        @endforeach
    </div>
    <h3 class="kpi-context-heading">Supporting reservation and data-quality metrics</h3>
    <div class="kpi-context-grid">
        @foreach($kpiContextCards as $card)
        <article class="kpi-card"><h3>{{ $card['title'] }}</h3><p class="kpi-value">{{ $card['value'] ?? 'N/A' }}</p>
            <details><summary>{{ str_contains($card['title'], 'Completeness') || str_contains($card['title'], 'Coverage') ? 'Definition and records to review' : 'Definition and matching reservations' }} ({{ $card['evidence']->count() }})</summary><p class="kpi-definition">{{ $card['definition'] }}</p>@include('dashboard.partials.performance-records', ['evidenceRows' => $card['evidence']])</details>
        </article>
        @endforeach
    </div>
    <details class="kpi-target-settings" id="kpi-target-settings" @if($errors->any()) open @endif>
        <summary>Configure administrator-reviewed KPI targets</summary>
        <p>Set targets only after agreement with MCST. These values are administrator-configured goals, not industry benchmarks. Leave a target blank if no goal has been agreed. Saving a target does not change reservations or approval rules.</p>
        <form method="POST" action="{{ route('admin.dashboard.kpi-targets') }}">@csrf @method('PUT')
            @foreach($filters as $scopeKey => $scopeValue)<input type="hidden" name="{{ $scopeKey }}" value="{{ $scopeValue }}">@endforeach
            <div class="kpi-target-grid">@foreach(\App\Services\DashboardKpiService::TARGETS as $targetKey => $definition)
                <label>{{ $definition['label'] }} ({{ $definition['unit'] }})<input type="number" name="kpi_{{ $targetKey }}" min="0" max="{{ $definition['max'] }}" step="{{ $targetKey === 'overdue' ? '1' : '0.1' }}" value="{{ old('kpi_'.$targetKey, $kpiTargets['kpi_'.$targetKey] ?? '') }}" placeholder="Not configured"></label>
            @endforeach</div>
            <label class="kpi-reviewed"><input type="checkbox" name="targets_reviewed" value="1" required> I have reviewed these targets for use by MCST.</label>
            <button type="submit" class="btn-primary">Save KPI targets</button>
        </form>
        @if($kpiTargets['kpi_updated_at'] ?? null)<p>Targets last updated: {{ \Carbon\Carbon::parse($kpiTargets['kpi_updated_at'])->format('M j, Y g:i A') }} ({{ config('app.timezone') }}). Changes are recorded in the audit trail.</p>@endif
    </details>
</section>
