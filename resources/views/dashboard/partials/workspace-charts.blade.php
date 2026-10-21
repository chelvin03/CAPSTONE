    <section class="reference-panel admin-panel"><header><div><h2>Monthly Reservation Trend — {{ $selectedYear === 'all' ? 'All years' : $selectedYear }}</h2><p>Number of reservations per month.</p></div></header>
        @include('dashboard.partials.reference-chart', ['rows' => $monthly, 'chartWidth' => 660, 'chartLabel' => 'Monthly Reservation Trend, number of reservations by scheduled event month'])
        <p class="analysis-axis">Number of Reservations · selected event-date period</p>
    </section>
