<section class="decision-support" aria-labelledby="decision-support-heading">
    <header><div><p class="decision-eyebrow">DATA → FINDING → DECISION</p><h2 id="decision-support-heading">Insights and suggested actions</h2><p>Findings from the selected reservation records, with actions for administrator review.</p></div><a class="btn-primary" href="{{ route('admin.dashboard', $filters + ['export' => 'csv']) }}">Export BI report</a></header>
    @if($decisionSampleNote)<p class="decision-note" role="status">{{ $decisionSampleNote }}</p>@endif
    <div class="decision-grid">@foreach($decisionCards as $card)<article><h3>{{ $card['title'] }}</h3><p class="decision-finding">{{ $card['finding'] }}</p><div class="decision-action"><strong>Suggested action</strong><p>{{ $card['action'] }}</p></div><details><summary>Evidence and interpretation</summary><p>{{ $card['evidence'] }}</p></details></article>@endforeach</div>
    <p class="decision-footnote">Based on scheduled reservation dates, including future bookings in the selected period. Suggestions are descriptive decision support; they do not predict demand or change schedules automatically.</p>
</section>
