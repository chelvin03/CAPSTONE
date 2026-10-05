@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<div class="admin-reports mx-auto max-w-7xl space-y-6">
    <div><h1 class="text-3xl font-bold text-[#1E3A8A]">Reports</h1><p class="mt-2 text-sm text-slate-500">Generate and download gymnasium reservation and utilization reports.</p></div>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="generate-title">
        <h2 id="generate-title" class="text-lg font-semibold text-[#1E3A8A]">Generate New Report</h2>
        @if ($errors->any())<div role="alert" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
        <form method="GET" action="{{ route('admin.reports.index') }}" class="mt-5 grid items-end gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label><span class="field-label">Report Type</span><select name="report_type" class="field w-full" required>
                @foreach ($types as $value => $label)<option value="{{ $value }}" @selected(old('report_type', $filters['report_type']) === $value)>{{ $label }}</option>@endforeach
            </select></label>
            <label><span class="field-label">Start Date</span><input id="report-start" type="date" name="date_from" value="{{ old('date_from', $filters['date_from']) }}" class="field w-full" required></label>
            <label><span class="field-label">End Date</span><input type="date" name="date_to" value="{{ old('date_to', $filters['date_to']) }}" class="field w-full" required x-data x-on:focus="$el.min = document.getElementById('report-start').value"></label>
            <button class="btn-primary bg-[#2563EB]" type="submit">Generate Report</button>
        </form>
        <p class="mt-4 text-xs text-slate-500">Dates refer to the reservation/event date. Both start and end dates are included.</p>
    </section>
    <section aria-labelledby="quick-title">
        <h2 id="quick-title" class="mb-3 text-lg font-semibold text-[#1E3A8A]">Quick Reports</h2>
        <p class="mb-4 text-sm text-slate-500">Current month: {{ $defaults['date_from'] }} to {{ $defaults['date_to'] }}.</p>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ([['reservation', 'Monthly Reservation Report', 'View reservations scheduled this month.', 'calendar3'], ['utilization', 'Gymnasium Utilization Report', 'Review approved booked hours and available capacity.', 'clock'], ['status', 'Reservation Status Report', 'See reservation counts by current status.', 'list-check'], ['cancellation', 'Cancellation Report', 'View cancelled bookings scheduled this month.', 'x-circle']] as [$type, $name, $description, $icon])
                <a href="{{ route('admin.reports.index', array_merge($defaults, ['report_type' => $type])) }}" class="flex gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 focus-visible:ring-2 focus-visible:ring-blue-600">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#2563EB]"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                    <div><h3 class="font-semibold text-[#1E3A8A]">{{ $name }}</h3><p class="mt-1 text-sm text-slate-500">{{ $description }}</p><span class="mt-3 inline-block text-sm font-semibold text-[#2563EB]">Generate &rarr;</span></div>
                </a>
            @endforeach
        </div>
    </section>
    @if ($data)
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="results-title">
            <div class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><h2 id="results-title" class="text-lg font-semibold text-[#1E3A8A]">{{ $data['title'] }}</h2><p class="mt-1 text-sm text-slate-500">{{ $filters['date_from'] }} to {{ $filters['date_to'] }} · Generated {{ $data['generatedAt']->format('M d, Y g:i A') }}</p></div>
                    <div class="flex flex-wrap gap-2"><a class="btn-secondary" href="{{ route('admin.reports.pdf', $filters) }}">Download PDF</a><a class="btn-primary bg-[#2563EB]" href="{{ route('admin.reports.excel', $filters) }}">Export Excel</a></div>
                </div>
                <dl class="mt-5 flex flex-wrap gap-x-10 gap-y-4">@foreach ($data['metrics'] as $label => $value)<div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 text-xl font-semibold text-[#1E3A8A]">{{ is_numeric($value) ? number_format($value, is_float($value) ? 2 : 0) : $value }}</dd></div>@endforeach</dl>
                <p class="mt-4 text-xs leading-relaxed text-slate-500">{{ $data['note'] }}</p>
            </div>
            @if ($data['total'] === 0)<p role="status" class="border-t border-slate-200 p-5 text-sm text-slate-500">No reservations match the selected period.</p>@endif
            <div class="overflow-x-auto"><table class="w-full text-left text-sm">
                <caption class="sr-only">{{ $data['title'] }} for {{ $filters['date_from'] }} to {{ $filters['date_to'] }}</caption>
                <thead class="border-y border-slate-200 bg-slate-50 text-slate-600"><tr>@foreach ($data['headers'] as $heading)<th scope="col" class="whitespace-nowrap px-5 py-3 font-semibold">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($data['rows'] ?? $records as $record)
                        <tr>@foreach ($data['rows'] !== null ? $record : $service->reportRow($record) as $cell)<td class="px-5 py-3 text-slate-700">{{ $cell }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($data['headers']) }}" class="p-8 text-center text-slate-500">No records to display.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @if ($records && $records->hasPages())<div class="border-t border-slate-200 p-5">{{ $records->links() }}</div>@endif
        </section>
    @endif
</div>
@endsection
