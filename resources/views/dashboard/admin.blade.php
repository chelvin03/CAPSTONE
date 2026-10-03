@extends('layouts.app')
@section('title', 'Business Intelligence Dashboard')
@section('content')
@php
    $label = fn ($value) => \App\Services\DashboardAnalyticsService::label($value);
@endphp
<div class="page-shell bi-dashboard">
    <header class="page-header">
        <div>
            <p class="text-sm font-semibold text-blue-700">Administration / Analytics</p>
            <h1 class="page-title mt-1">Business Intelligence Dashboard</h1>
            <p class="page-description">Reservation demand, scheduled utilization, and decisions needing attention.</p>
        </div>
        <a href="{{ route('admin.reservations.index') }}" class="btn-primary shrink-0">Manage Reservations</a>
    </header>

    <section class="card mb-6 p-5" aria-label="Dashboard filters">
        @if ($errors->any())
            <div class="alert-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="GET" action="{{ route('admin.dashboard') }}" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <label class="text-sm font-semibold">From date<input class="field mt-2" type="date" name="date_from" value="{{ $filters['date_from'] }}" required></label>
            <label class="text-sm font-semibold">To date<input class="field mt-2" type="date" name="date_to" value="{{ $filters['date_to'] }}" required></label>
            <label class="text-sm font-semibold">Event type<select class="field mt-2" name="category"><option value="">All event types</option>@foreach ($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $label($category) }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Requestor type<select class="field mt-2" name="requestor_type"><option value="">All requestor types</option>@foreach ($requestorTypes as $type)<option value="{{ $type }}" @selected(($filters['requestor_type'] ?? '') === $type)>{{ $label($type) }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Reservation status<select class="field mt-2" name="status"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label($status) }}{{ $status === 'pending' ? ' (legacy)' : '' }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Trend grouping<select class="field mt-2" name="grouping">@foreach (['daily', 'weekly', 'monthly', 'quarterly', 'annual'] as $group)<option value="{{ $group }}" @selected($filters['grouping'] === $group)>{{ ucfirst($group) }}</option>@endforeach</select></label>
            @if ($facilities->count() > 1)
                <label class="text-sm font-semibold">Facility<select class="field mt-2" name="facility_id"><option value="">All facilities</option>@foreach ($facilities as $facility)<option value="{{ $facility->id }}" @selected(($filters['facility_id'] ?? '') == $facility->id)>{{ $facility->facility_name }}</option>@endforeach</select></label>
            @endif
            <div class="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-3">
                <button class="btn-primary" type="submit">Apply filters</button>
                <a class="btn-secondary" href="{{ route('admin.dashboard') }}">Reset</a>
                <a class="btn-secondary" href="{{ route('admin.dashboard', array_merge($filters, ['export' => 'csv'])) }}">Export filtered report (CSV)</a>
            </div>
        </form>
    </section>

    <div class="mb-4 space-y-2 text-sm text-slate-600">
        <div class="flex flex-wrap justify-between gap-2">
            <p><strong class="text-slate-900">Reporting period: {{ $filters['date_from'] }} to {{ $filters['date_to'] }}</strong></p>
            <p>Last updated: <time datetime="{{ $generatedAt->toIso8601String() }}">{{ $generatedAt->format('M d, Y g:i A T') }}</time></p>
        </div>
        <p>Active filters: {{ collect($filterLabels)->map(fn ($value, $name) => $name . ': ' . $value)->implode(' · ') }}</p>
        <a href="#reservation-details" class="inline-block font-semibold text-blue-700 underline">Explore {{ number_format($totalReservations) }} matching reservations</a>
    </div>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key performance indicators">
        <x-dashboard-stat title="Total Requests" :value="$totalReservations" detail="All matching reservation records" icon="&#128197;" icon-class="bg-blue-50" />
        <x-dashboard-stat title="Pending Review" :value="$pendingReservations" detail="New and validated requests" icon="&#9203;" icon-class="bg-purple-50" />
        <x-dashboard-stat title="Overdue Requests" :value="$oldPending" detail="Pending review for at least 7 days since submission" icon="!" icon-class="bg-amber-50" />
        <x-dashboard-stat title="Approved Reservations" :value="$approvedReservations" detail="Currently approved" icon="&#10003;" icon-class="bg-emerald-50" />
        <x-dashboard-stat title="Completed Events" :value="$completedEvents" :detail="$completionRecorded . ' with a completion timestamp'" icon="&#10003;" icon-class="bg-teal-50" />
        <x-dashboard-stat title="Scheduled Utilization Rate" :value="$utilizationRate === null ? 'N/A' : $utilizationRate . '%'" :detail="$availableHours === null ? 'Valid operating hours and a facility required' : $utilizedHours . ' occupied / ' . $availableHours . ' available hours'" icon="&#9201;" icon-class="bg-blue-50" />
        <x-dashboard-stat title="Cancellation Rate" :value="$cancellationRate === null ? 'N/A' : $cancellationRate . '%'" :detail="$cancelledReservations . ' cancelled / ' . $totalReservations . ' requests'" icon="&#10005;" icon-class="bg-rose-50" />
        <x-dashboard-stat title="Average Processing Time" :value="$processingHours === null ? 'N/A' : $processingHours . ' hrs'" :detail="$processingCount . ' records with a valid first decision timestamp'" icon="&#9201;" icon-class="bg-slate-100" />
    </section>

    <section class="card mt-6 p-6" aria-labelledby="trend-title">
        <h2 id="trend-title" class="font-bold">Reservation trend</h2>
        <p class="mt-1 text-sm text-slate-500">{{ ucfirst($filters['grouping']) }} reservation counts by scheduled date, including zero-activity periods. Select a bar to filter the dashboard. “Partial” marks a clipped reporting period.</p>
        <p class="mt-3 text-xs font-semibold text-slate-600">Vertical axis: number of reservations</p>
        <div class="mt-3 overflow-x-auto" tabindex="0" role="region" aria-label="Scrollable reservation trend">
            <div class="flex h-52 items-end gap-3 border-b border-slate-200 pb-3" style="min-width: {{ max(400, $trendActivity->count() * 72) }}px">
                @foreach ($trendActivity as $period)
                    <a href="{{ route('admin.dashboard', array_merge($filters, ['date_from' => $period['from'], 'date_to' => $period['to']])) }}" class="group flex h-full min-w-0 flex-1 flex-col justify-end text-center" aria-label="{{ $period['label'] }} {{ $period['year'] }}: {{ $period['count'] }} reservations, {{ $period['from'] }} to {{ $period['to'] }}{{ $period['partial'] ? ', partial period' : '' }}. Filter to this period.">
                        <span class="mb-2 text-xs font-semibold">{{ $period['count'] }}</span>
                        <span class="mx-auto block w-full max-w-10 rounded-t bg-blue-600 group-hover:bg-blue-800" style="height: {{ $period['count'] / $trendMaximum * 110 }}px"></span>
                        <span class="mt-2 text-xs text-slate-600">{{ $period['label'] }}<br>{{ $period['year'] }}</span>
                        <span class="h-4 text-xs text-amber-800">{{ $period['partial'] ? 'Partial' : '' }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Peak demand summary">
        @foreach (['Peak weekday' => $peakWeekday, 'Peak time slot' => $peakTimeSlot, 'Busiest month' => $busiestMonth] as $heading => $peak)
            <div class="card p-5"><h2 class="text-sm font-semibold text-slate-600">{{ $heading }}</h2><p class="mt-2 font-bold">{{ $peak['label'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $peak['count'] }} reservations{{ $peak['tied'] ? ' each (tie)' : '' }}</p></div>
        @endforeach
        <div class="card p-5"><h2 class="text-sm font-semibold text-slate-600">Matching reservations</h2><p class="mt-2 text-2xl font-bold">{{ number_format($totalReservations) }}</p><p class="mt-1 text-xs text-slate-500">All selected statuses. Partial months can affect comparisons.</p></div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card min-w-0 p-6">
            <h2 class="font-bold">Demand by Event Type</h2><p class="mt-1 text-xs text-slate-500">Counts and share of all matching requests. Select a named event type to filter.</p>
            <div class="mt-5 max-h-96 space-y-4 overflow-y-auto">
                @forelse ($eventDemand as $item)
                    <div>
                        <div class="mb-2 flex justify-between gap-3 text-sm">
                            @if ($item['category'] !== '')<a class="break-words text-blue-700 underline" href="{{ route('admin.dashboard', array_merge($filters, ['category' => $item['category']])) }}">{{ $item['label'] }}</a>@else<span>Not specified</span>@endif
                            <strong class="shrink-0">{{ $item['count'] }} <span class="text-xs font-normal text-slate-500">({{ $item['percentage'] }}%)</span></strong>
                        </div>
                        <div class="h-2 rounded bg-slate-100"><div class="h-2 rounded bg-blue-600" style="width: {{ $item['percentage'] }}%"></div></div>
                    </div>
                @empty <p class="text-sm text-slate-500">No event demand for these filters.</p> @endforelse
            </div>
        </section>
        <section class="card p-6">
            <h2 class="font-bold">Demand by Weekday</h2><p class="mt-1 text-xs text-slate-500">Scheduled reservations across the selected period.</p>
            <div class="mt-5 space-y-4">
                @foreach ($weekdayDemand as $item)
                    <div><div class="mb-1 flex justify-between text-sm"><span>{{ $item['label'] }}</span><strong>{{ $item['count'] }}</strong></div><div class="h-2 rounded bg-slate-100"><div class="h-2 rounded bg-teal-600" style="width: {{ $item['count'] / max(1, $weekdayDemand->max('count')) * 100 }}%"></div></div></div>
                @endforeach
            </div>
            <details class="mt-5 text-xs text-slate-600"><summary class="cursor-pointer font-semibold">Hourly demand counts</summary><p class="mt-2">A booking counts in each hour it touches. Overlaps count as separate demand, not actual utilization. Invalid or overnight intervals are excluded.</p><div class="mt-2 grid grid-cols-2 gap-2">@foreach ($timeSlots as $slot)<p>{{ $slot['label'] }}: <strong>{{ $slot['count'] }}</strong></p>@endforeach</div></details>
        </section>
        <section class="card p-6">
            <h2 class="font-bold">Reservation Outcomes</h2><p class="mt-1 text-xs text-slate-500">Current status of {{ $totalReservations }} matching reservations. Select a status to filter.</p>
            @php
                $pieStops = []; $pieCount = 0;
                foreach ($statusBreakdown as $item) {
                    if ($item['count'] > 0 && $totalReservations > 0) {
                        $start = $pieCount / $totalReservations * 100;
                        $pieCount += $item['count'];
                        $pieStops[] = ($statusColors[$item['status']] ?? '#64748b').' '.$start.'% '.($pieCount / $totalReservations * 100).'%';
                    }
                }
            @endphp
            @if ($totalReservations)
                <div class="mx-auto mt-5 rounded-full" style="width: 144px; max-width: 100%; aspect-ratio: 1; background: conic-gradient({{ implode(', ', $pieStops) }});" role="img" aria-label="Reservation outcomes: {{ $totalReservations }} matching reservations. Accessible counts and percentages in the legend below."></div>
            @else <p class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-500">No outcomes to chart for these filters.</p> @endif
            <ul class="mt-4 divide-y divide-slate-100" aria-label="Status legend, counts and percentages">
                @foreach ($statusBreakdown as $item)
                    <li><a class="flex items-center justify-between gap-3 py-2 text-sm hover:text-blue-700" href="{{ route('admin.dashboard', array_merge($filters, ['status' => $item['status']])) }}"><span class="flex items-center gap-2"><span class="inline-block h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $statusColors[$item['status']] ?? '#64748b' }}" aria-hidden="true"></span>{{ $label($item['status']) }}{{ $item['status'] === 'pending' ? ' (legacy)' : '' }}</span><span><strong>{{ $item['count'] }}</strong> <span class="ml-2 text-xs text-slate-500">{{ $item['percentage'] === null ? 'N/A' : $item['percentage'].'%' }}</span></span></a></li>
                @endforeach
            </ul>
        </section>
    </div>

    <section class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3" aria-label="Supporting analytics">
        <div class="card p-5"><h2 class="font-bold">Most-used equipment</h2><p class="mt-1 text-xs text-slate-500">Top five by allocated units, then booking count. Allocations are not measured physical use.</p><ul class="mt-3 space-y-3 text-sm">@forelse ($equipmentDemand as $equipment)<li><strong>{{ $equipment->equipment_name }}</strong><br>{{ $equipment->allocated }} allocated / {{ $equipment->requested }} requested units across {{ $equipment->bookings }} bookings</li>@empty<li>No equipment allocations recorded for these filters.</li>@endforelse</ul></div>
        <div class="card p-5"><h2 class="font-bold">Satisfaction feedback</h2><p class="mt-3 text-2xl font-bold">{{ $feedbackAverage ?? 'N/A' }}</p><p class="mt-1 text-sm text-slate-600">Average recorded rating · {{ $feedbackCount }} responses</p><p class="mt-3 text-xs text-slate-500">{{ $feedbackCount ? 'Only recorded feedback is included; nonresponses are not scored.' : 'No feedback recorded for these reservations.' }} The database has no configured rating scale.</p></div>
        <div class="card p-5"><h2 class="font-bold">Cancellation reasons</h2><ul class="mt-3 space-y-2 text-sm">@forelse ($cancellationReasons as $reason)<li class="flex justify-between gap-3"><span>{{ $reason->reason_group }}</span><strong>{{ $reason->total }}</strong></li>@empty<li class="text-slate-500">No cancelled requests in this selection.</li>@endforelse</ul><p class="mt-3 text-xs text-slate-500">Keyword groups from recorded reasons. Personal free-text details are omitted.</p></div>
        <div class="card p-5"><h2 class="font-bold">Waiting-list conversion</h2><p class="mt-3 text-sm">{{ $waitingCount }} currently waiting. {{ $waitingPromoted }} of {{ $waitingEver }} reservations with waiting-list history later reached approved status.</p><p class="mt-2 font-bold">{{ $waitingConversion === null ? 'N/A — no waiting-list history' : $waitingConversion.'% conversion' }}</p><p class="mt-3 text-xs text-slate-500">History-based, within the currently filtered reservation cohort. Not a prediction.</p></div>
        <div class="card p-5"><h2 class="font-bold">Approved versus completed</h2><p class="mt-3 text-sm"><strong>{{ $approvedReservations }}</strong> currently approved · <strong>{{ $completedEvents }}</strong> completed</p><p class="mt-3 text-xs text-slate-500">{{ $completionRecorded }} completed events have a completion timestamp. Completion status does not measure actual duration or attendance.</p></div>
        <div class="card p-5"><h2 class="font-bold">Data coverage</h2><p class="mt-3 text-sm text-slate-600">Actual attendance and actual start/end times are not stored. Requestor groups use the recorded reservation type, including legacy groups. Empty fields are shown as “Not specified.”</p></div>
    </section>

    <section id="reservation-details" class="card mt-6 scroll-mt-20 overflow-hidden">
        <div class="border-b border-slate-100 p-6"><h2 class="font-bold">Matching reservations <span class="font-normal text-slate-500">({{ number_format($recentReservations->total()) }})</span></h2><p class="mt-1 text-sm text-slate-500">Ordered by scheduled date, start time, then record ID, earliest first.</p></div>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Scrollable matching reservations table"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <caption class="sr-only">Reservations matching all dashboard filters, {{ $recentReservations->total() }} records</caption>
            <thead class="bg-slate-50"><tr>@foreach (['Reference Number', 'Event Name', 'Event Type', 'Requestor Type', 'Facility', 'Scheduled Date', 'Start / End Time', 'Expected Attendees', 'Status', 'Action'] as $heading)<th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recentReservations as $reservation)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-4 font-medium">{{ $reservation->reference_number }}</td>
                        <td class="min-w-40 px-4 py-4">{{ $label($reservation->event_name) }}</td>
                        <td class="px-4 py-4">{{ $label($reservation->event_type) }}</td>
                        <td class="px-4 py-4">{{ $label($reservation->reservation_type) }}</td>
                        <td class="px-4 py-4">{{ $reservation->facility?->facility_name ?? 'Unknown facility' }}</td>
                        <td class="whitespace-nowrap px-4 py-4">{{ $reservation->reservation_date->format('M d, Y') }}</td>
                        <td class="whitespace-nowrap px-4 py-4">{{ substr($reservation->start_time, 0, 5) }} – {{ substr($reservation->end_time, 0, 5) }}</td>
                        <td class="px-4 py-4">{{ number_format($reservation->expected_attendees) }}</td>
                        <td class="px-4 py-4"><span class="status-badge whitespace-nowrap border bg-white" style="color: {{ $statusColors[$reservation->status] ?? '#64748b' }}; border-color: {{ $statusColors[$reservation->status] ?? '#64748b' }}">{{ $label($reservation->status) }}</span></td>
                        <td class="px-4 py-4"><a class="font-semibold text-blue-700 underline" href="{{ route('admin.reservations.show', $reservation) }}" aria-label="View {{ $reservation->reference_number }}">View</a></td>
                    </tr>
                @empty <tr><td colspan="10" class="px-6 py-10 text-center text-slate-500">No reservations match these filters. Try a wider date range or reset your filters.</td></tr> @endforelse
            </tbody>
        </table></div>
        @if ($recentReservations->hasPages())<div class="border-t border-slate-100 p-5">{{ $recentReservations->links() }}</div>@endif
    </section>
</div>
@endsection
