@extends('layouts.app')
@section('title', 'Business Intelligence Dashboard')
@section('content')
<div class="page-shell">
    <header class="page-header">
        <div>
            <p class="text-sm font-semibold text-blue-700">Administration / Analytics</p>
            <h1 class="page-title mt-1">Business Intelligence Dashboard</h1>
            <p class="page-description">Understand reservation demand, outcomes, and requests needing attention.</p>
        </div>
        <a href="{{ route('admin.reservations.create') }}" class="btn-primary">+ New Reservation</a>
    </header>

    <section class="card mb-6 p-5" aria-label="Dashboard filters">
        @if ($errors->any())
            <div class="alert-error" role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="GET" action="{{ route('admin.dashboard') }}" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <label class="text-sm font-semibold">From<input class="field mt-2" type="date" name="date_from" value="{{ $filters['date_from'] }}" required></label>
            <label class="text-sm font-semibold">To<input class="field mt-2" type="date" name="date_to" value="{{ $filters['date_to'] }}" required></label>
            <label class="text-sm font-semibold">Facility<select class="field mt-2" name="facility_id"><option value="">All facilities</option>@foreach ($facilities as $facility)<option value="{{ $facility->id }}" @selected(($filters['facility_id'] ?? '') == $facility->id)>{{ $facility->facility_name }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Category<select class="field mt-2" name="category"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ ucwords(str_replace('_', ' ', $category)) }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Status<select class="field mt-2" name="status"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></label>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-5">
                <button class="btn-primary" type="submit">Apply filters</button>
                <a class="btn-secondary" href="{{ route('admin.dashboard') }}">Reset</a>
                <a class="btn-secondary" href="{{ route('admin.dashboard', array_merge($filters, ['export' => 'csv'])) }}">Export selected data (CSV)</a>
                <span class="text-xs text-slate-500">All panels use these filters and scheduled reservation dates.</span>
            </div>
        </form>
    </section>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600">
        <p><strong class="text-slate-900">{{ \Carbon\Carbon::parse($filters['date_from'])->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($filters['date_to'])->format('M d, Y') }}</strong></p>
        <a href="#reservation-details" class="font-semibold text-blue-700 underline">Explore {{ number_format($totalReservations) }} matching reservations</a>
    </div>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key performance indicators">
        <x-dashboard-stat title="Total reservations" :value="$totalReservations" :detail="$change === null ? 'No prior-period baseline' : ($change > 0 ? '+' : '') . $change . '% vs previous period'" icon="&#128197;" icon-class="bg-blue-50" />
        <x-dashboard-stat title="Approved / completed" :value="$approvedReservations + $completedEvents" :detail="$approvedReservations . ' approved · ' . $completedEvents . ' completed'" icon="&#10003;" icon-class="bg-emerald-50" />
        <x-dashboard-stat title="Cancellation rate" :value="$cancellationRate === null ? 'N/A' : $cancellationRate . '%'" :detail="$cancelledReservations . ' cancelled of ' . $totalReservations . ' reservations'" icon="&#10005;" icon-class="bg-rose-50" />
        <x-dashboard-stat title="Pending requests" :value="$pendingReservations" :detail="$oldPending . ' submitted at least 7 days ago'" icon="&#9203;" icon-class="bg-amber-50" />
    </section>

    <section class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-6" aria-labelledby="insights-title">
        <h2 id="insights-title" class="font-bold text-slate-900">Decision insights</h2>
        <div class="mt-3 grid gap-5 text-sm text-slate-700 lg:grid-cols-3">
            <div><h3 class="font-semibold text-blue-900">Demand comparison</h3><p class="mt-1">{{ number_format($totalReservations) }} reservations versus {{ number_format($previousTotal) }} in the preceding {{ $previousFrom->diffInDays($previousTo) + 1 }} days ({{ $previousFrom->format('M d, Y') }} &ndash; {{ $previousTo->format('M d, Y') }}). {{ $change === null ? 'Percentage change is unavailable because the prior period has no matching reservations.' : 'Use this comparison when planning staffing and equipment.' }}</p></div>
            <div><h3 class="font-semibold text-blue-900">Facility demand</h3><p class="mt-1">@if($facilityDemand->isNotEmpty()) {{ $facilityDemand->first()['label'] }} has the highest reservation count ({{ $facilityDemand->first()['count'] }}{{ $facilityDemand->where('count', $facilityDemand->first()['count'])->count() > 1 ? ', tied' : '' }}). Review demand before allocating facility support. @else No matching reservations. Broaden the filters to explore demand. @endif</p></div>
            <div><h3 class="font-semibold text-blue-900">Requests to review</h3><p class="mt-1">{{ $oldPending }} pending requests were submitted at least seven days ago. @if($oldPending) Review their requirements and availability to identify delays. @else No aged pending requests in this selection. @endif Expected attendees for approved/completed bookings: <strong>{{ number_format($expectedAttendees) }}</strong>.</p></div>
        </div>
    </section>

    <section class="card mt-6 p-6" aria-labelledby="trend-title">
        <h2 id="trend-title" class="font-bold">Reservation trend</h2>
        <p class="mt-1 text-sm text-slate-500">Monthly counts, including zero-activity months. Select a month to filter the dashboard. Boundary months may be partial.</p>
        <div class="mt-5 overflow-x-auto">
            <div class="flex h-64 items-end gap-3 border-b border-slate-200 pb-3" style="min-width: {{ max(500, $monthlyActivity->count() * 65) }}px">
                @foreach ($monthlyActivity as $month)
                    <a href="{{ route('admin.dashboard', array_merge($filters, ['date_from' => $month['from'], 'date_to' => $month['to']])) }}" class="group flex h-full min-w-0 flex-1 flex-col justify-end text-center" aria-label="{{ $month['label'] }} {{ $month['year'] }}: {{ $month['count'] }} reservations. Filter to this month.">
                        <span class="mb-2 text-xs font-semibold">{{ $month['count'] }}</span>
                        <span class="mx-auto block w-full max-w-10 rounded-t bg-blue-600 group-hover:bg-blue-800" style="height: {{ ($month['count'] / $monthlyMaximum) * 160 }}px"></span>
                        <span class="mt-2 text-xs text-slate-600">{{ $month['label'] }}<br>{{ $month['year'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card p-6">
            <h2 class="font-bold">Facility demand</h2><p class="mt-1 text-xs text-slate-500">Reservation count, not hours of utilization. Select a facility to explore.</p>
            <div class="mt-5 space-y-4">
                @forelse ($facilityDemand as $item)
                    <a class="block rounded focus-visible:ring-2" href="{{ route('admin.dashboard', array_merge($filters, ['facility_id' => $item['id']])) }}">
                        <span class="mb-2 flex justify-between gap-3 text-sm"><span class="text-blue-700">{{ $item['label'] }}</span><strong>{{ $item['count'] }}</strong></span>
                        <span class="block h-2 rounded bg-slate-100"><span class="block h-2 rounded bg-blue-600" style="width: {{ $item['count'] / max(1, $facilityDemand->max('count')) * 100 }}%"></span></span>
                    </a>
                @empty <p class="text-sm text-slate-500">No facility demand for these filters.</p> @endforelse
            </div>
        </section>
        <section class="card p-6">
            <h2 class="font-bold">Demand by weekday</h2><p class="mt-1 text-xs text-slate-500">Scheduled reservations across the selected period.</p>
            <div class="mt-5 space-y-4">
                @foreach ($weekdayDemand as $item)
                    <div><div class="mb-1 flex justify-between text-sm"><span>{{ $item['label'] }}</span><strong>{{ $item['count'] }}</strong></div><div class="h-2 rounded bg-slate-100"><div class="h-2 rounded bg-teal-600" style="width: {{ $item['count'] / max(1, $weekdayDemand->max('count')) * 100 }}%"></div></div></div>
                @endforeach
            </div>
        </section>
        <section class="card p-6">
            <h2 class="font-bold">Reservation outcomes</h2><p class="mt-1 text-xs text-slate-500">Current status. Select a status to explore its records.</p>
            @php
                $statusColors = [
                    'new' => '#2563eb', 'pending' => '#d97706',
                    'validated' => '#0891b2', 'waiting_list' => '#7c3aed',
                    'approved' => '#059669', 'completed' => '#334155',
                    'rejected' => '#e11d48', 'cancelled' => '#94a3b8',
                ];
                $pieStops = [];
                $pieCount = 0;
                foreach ($statusBreakdown as $item) {
                    if ($item['count'] > 0 && $totalReservations > 0) {
                        $start = $pieCount / $totalReservations * 100;
                        $pieCount += $item['count'];
                        $end = $pieCount / $totalReservations * 100;
                        $pieStops[] = $statusColors[$item['status']].' '.$start.'% '.$end.'%';
                    }
                }
            @endphp
            @if ($totalReservations > 0)
                <div class="mx-auto mt-5 rounded-full" style="width: 192px; max-width: 100%; aspect-ratio: 1; background: conic-gradient({{ implode(', ', $pieStops) }});" role="img" aria-label="Reservation outcomes pie chart: {{ number_format($totalReservations) }} matching reservations. Counts and percentages by status are listed below."></div>
                <p class="mt-3 text-center text-sm text-slate-500">{{ number_format($totalReservations) }} matching reservations</p>
            @else
                <p class="mt-5 rounded-lg bg-slate-50 p-4 text-center text-sm text-slate-500">No reservation outcomes to chart for these filters.</p>
            @endif
            <div class="mt-4 divide-y divide-slate-100">
                @foreach ($statusBreakdown as $item)
                    <a class="flex items-center justify-between gap-3 py-3 text-sm hover:text-blue-700" href="{{ route('admin.dashboard', array_merge($filters, ['status' => $item['status']])) }}"><span class="flex items-center gap-2"><span class="inline-block h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $statusColors[$item['status']] }}" aria-hidden="true"></span>{{ ucwords(str_replace('_', ' ', $item['status'])) }}</span><span><strong>{{ $item['count'] }}</strong> <span class="ml-2 text-xs text-slate-500">{{ $totalReservations ? round($item['count'] / $totalReservations * 100, 1) . '%' : 'N/A' }}</span></span></a>
                @endforeach
            </div>
        </section>
    </div>

    <section id="reservation-details" class="card mt-6 scroll-mt-20 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-6"><div><h2 class="font-bold">Matching reservations</h2><p class="mt-1 text-sm text-slate-500">Source records for the selected metrics, most recently updated first.</p></div><a class="text-sm font-semibold text-blue-700" href="{{ route('admin.reservations.index') }}">Manage all reservations</a></div>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <caption class="sr-only">Reservations matching all dashboard filters</caption>
            <thead class="bg-slate-50"><tr>@foreach (['Reference', 'Event / facility', 'Date', 'Status', 'Action'] as $heading)<th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recentReservations as $reservation)
                    <tr class="hover:bg-slate-50"><td class="whitespace-nowrap px-6 py-4 font-medium">{{ $reservation->reference_number }}</td><td class="px-6 py-4">{{ $reservation->event_name }}<span class="mt-1 block text-xs text-slate-500">{{ $reservation->facility?->facility_name ?? 'Unknown facility' }}</span></td><td class="whitespace-nowrap px-6 py-4">{{ $reservation->reservation_date->format('M d, Y') }}</td><td class="whitespace-nowrap px-6 py-4">{{ ucwords(str_replace('_', ' ', $reservation->status)) }}</td><td class="px-6 py-4"><a class="font-semibold text-blue-700 underline" href="{{ route('admin.reservations.show', $reservation) }}" aria-label="View {{ $reservation->reference_number }}">View</a></td></tr>
                @empty <tr><td colspan="5" class="px-6 py-10 text-center text-slate-500">No reservations match these filters. Try a wider date range or reset your filters.</td></tr> @endforelse
            </tbody>
        </table></div>
        @if ($recentReservations->hasPages())<div class="border-t border-slate-100 p-5">{{ $recentReservations->links() }}</div>@endif
    </section>
    <details class="card mt-6 p-5 text-sm text-slate-600">
        <summary class="cursor-pointer font-semibold text-slate-900">Metric definitions and data scope</summary>
        <ul class="mt-3 list-disc space-y-2 pl-5">
            <li>Default range: the current calendar month and the preceding 11 months. Dates are inclusive and refer to the scheduled reservation date; future bookings within the range are included.</li>
            <li>Every panel, detail row, and CSV uses the selected date, facility, category, and status filters. Counts represent reservations, including cancelled and rejected requests unless filtered out.</li>
            <li>Cancellation rate = currently cancelled reservations / all matching reservations × 100. No matching data is shown as N/A, not a measured zero rate.</li>
            <li>Pending includes new, pending, validated, and waiting-list requests. Age is time since submission, not time spent in the current status.</li>
            <li>Comparison uses the immediately preceding equal number of calendar days with identical non-date filters. Change = (current count − previous count) / previous count × 100; unavailable when the previous count is zero.</li>
            <li>Expected attendees sums approved and completed bookings only. It is an estimate, not actual attendance or a count of unique people. Facility demand measures reservation counts, not occupancy or available-hour utilization.</li>
            <li>Status reflects the current database state, including for historical periods. Data refreshes when this page is loaded. CSV contains all matching records, not just this table page.</li>
        </ul>
    </details>
</div>
@endsection
