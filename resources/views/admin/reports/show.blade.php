@extends('layouts.app')

@section('title', 'Report Details')

@section('content')
<div class="mx-auto max-w-6xl">
    <a href="{{ route('admin.reports.index') }}" class="text-sm font-semibold text-blue-600 hover:underline">← Back to Reports</a>
    <div class="mt-4 mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col justify-between gap-4 sm:flex-row">
            <div><p class="text-sm font-semibold text-blue-600">{{ ucfirst($report->report_type) }} Report</p><h1 class="mt-1 text-3xl font-bold text-slate-900">{{ $report->period_start->format('M d, Y') }} – {{ $report->period_end->format('M d, Y') }}</h1></div>
            <div class="text-sm text-slate-500">Submitted by <span class="font-semibold text-slate-900">{{ $report->submitter->full_name }}</span><br>{{ $report->submitted_at->format('M d, Y g:i A') }}</div>
        </div>
    </div>

    <div class="mb-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-dashboard-stat title="Reservations" :value="$report->total_reservations" detail="Total requests" icon="&#128197;" icon-class="bg-blue-50" />
        <x-dashboard-stat title="Approved" :value="$report->approved_reservations" detail="Approved requests" icon="&#10003;" icon-class="bg-emerald-50" />
        <x-dashboard-stat title="Completed" :value="$report->completed_reservations" detail="Completed events" icon="&#127937;" icon-class="bg-violet-50" />
        <x-dashboard-stat title="Expected Attendees" :value="$report->total_attendees" detail="Combined attendance" icon="&#128101;" icon-class="bg-amber-50" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach (['Reservations by Status' => $report->status_counts, 'Reservations by Facility' => $report->facility_counts] as $heading => $counts)
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm"><h2 class="border-b border-slate-100 px-6 py-5 font-semibold">{{ $heading }}</h2><div class="divide-y divide-slate-100 px-6">
                @forelse ($counts as $label => $count)<div class="flex justify-between py-4"><span class="text-sm text-slate-600">{{ ucwords(str_replace('_', ' ', $label)) }}</span><span class="font-semibold">{{ number_format($count) }}</span></div>@empty<p class="py-8 text-center text-sm text-slate-500">No data reported.</p>@endforelse
            </div></section>
        @endforeach
    </div>
</div>
@endsection
