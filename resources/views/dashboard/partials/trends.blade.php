<details class="card mt-6 p-6">
    <summary class="cursor-pointer font-bold text-blue-800">View Monthly Summary — {{ $dashboardTrendYear }}</summary>
    <p class="mt-3 text-sm text-slate-500">Reservations by scheduled month for the whole selected year. Approved includes completed bookings. Disapproved means rejected; pending, pencil holds, waiting-list and cancelled records remain separate. Percentages use all reservations in each month, so approved and disapproved may not add to 100%.</p>
    <div class="mt-4 overflow-x-auto" tabindex="0" role="region" aria-label="Monthly reservation summary"><table class="w-full text-left text-sm">
        <thead><tr class="border-b">@foreach(['Month','Total Reservations','Approved','Approved %','Disapproved','Disapproved %','Other Statuses'] as $heading)<th class="whitespace-nowrap p-3">{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>@foreach($dashboardMonthlyOutcomes as $row)<tr class="border-b"><th class="p-3">{{ $row['label'] }}</th><td class="p-3">{{ $row['total'] }}</td><td class="p-3 text-green-800">{{ $row['approved'] }}</td><td class="p-3 text-green-800">{{ number_format($row['approved_percent'], 1) }}%</td><td class="p-3 text-red-700">{{ $row['disapproved'] }}</td><td class="p-3 text-red-700">{{ number_format($row['disapproved_percent'], 1) }}%</td><td class="p-3">{{ $row['other'] }}</td></tr>@endforeach</tbody>
    </table></div>
</details>
<section class="card mt-6 p-6" aria-labelledby="monthly-trend-title">
    <h2 id="monthly-trend-title" class="font-bold">Monthly Reservation Trend — {{ $dashboardTrendYear }}</h2>
    <p class="mt-2 text-sm text-slate-500">All 12 months are shown, including months with zero schedules. Counts use reservation dates and the selected event, requestor, status and facility filters. Future dates show reservations already entered.</p>
    @include('dashboard.partials.trend-bars', ['rows' => $dashboardMonthlyTrend, 'chartLabel' => 'Monthly reservation counts'])
</section>
<section class="card mt-6 p-6" aria-labelledby="yearly-trend-title">
    <h2 id="yearly-trend-title" class="font-bold">Yearly Reservation Trend</h2>
    <p class="mt-2 text-sm text-slate-500">Recorded years, including intervening years with zero schedules. Current and future years may be incomplete. These trends extend beyond the summary's date filter.</p>
    @include('dashboard.partials.trend-bars', ['rows' => $dashboardYearlyTrend, 'chartLabel' => 'Yearly reservation counts'])
</section>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="card p-6">
        <h2 class="font-bold">Months with the Most Schedules</h2>
        <p class="mt-2 text-sm text-slate-500">The top calendar months across recorded history before this month. This describes past schedules; it does not guarantee a booking every year.</p>
        <ul class="mt-4 space-y-3">
            @forelse($dashboardBusyMonths as $row)
                <li class="flex flex-wrap justify-between gap-2"><strong>{{ $row['label'] }}</strong><span>{{ $row['count'] }} reservations across {{ $row['years'] }} {{ $row['years'] === 1 ? 'year' : 'years' }}</span></li>
            @empty
                <li>No historical schedules for these filters.</li>
            @endforelse
        </ul>
    </section>
    <section class="card p-6">
        <h2 class="font-bold">Reservation Forecast — Next 3 Months</h2>
        @if($dashboardForecastMonths >= 3)
            <p class="mt-2 text-sm text-slate-500">Baseline estimate using the average of {{ $dashboardForecastMonths }} completed months ({{ $dashboardForecastFrom }}–{{ $dashboardForecastTo }}), including zero months. It excludes this incomplete month and future schedules. This estimate does not model seasonality and is not a confirmed booking count.</p>
            <table class="mt-4 w-full text-sm"><thead><tr><th class="py-2 text-left">Month</th><th class="py-2 text-right">Estimated reservations</th></tr></thead><tbody>
                @foreach($dashboardForecast as $row)<tr><td class="py-2">{{ $row['label'] }}</td><td class="py-2 text-right font-semibold">{{ number_format($row['estimate'], 1) }}</td></tr>@endforeach
            </tbody></table>
        @else
            <p class="mt-4 text-sm text-slate-500">Not enough history to estimate demand. At least 3 completed calendar months from the earliest matching schedule are required.</p>
        @endif
    </section>
</div>
