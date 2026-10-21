    <section id="cancellation-chart" class="reference-panel admin-panel analysis-cancellation"><header><div><h2>Cancellation Rate Trend — {{ $selectedYear === 'all' ? 'All years' : $selectedYear }}</h2><p>Monthly cancellation percentage for the selected period.</p></div>@include('dashboard.partials.chart-year-filter', ['yearFilterId' => 'cancellation-year', 'yearFilterLabel' => 'Cancellation analysis year'])</header>
        @include('dashboard.partials.cancellation-timeline')
    </section>
