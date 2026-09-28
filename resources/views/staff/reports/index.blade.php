@extends('layouts.app')

@section('title', 'Analytics Reports')

@section('content')
<div class="mx-auto max-w-7xl">
    <div class="mb-6">
        <p class="text-sm font-semibold text-blue-600">Analytics</p>
        <h1 class="text-3xl font-bold text-slate-900">Reservation Reports</h1>
        <p class="mt-1 text-sm text-slate-500">Generate reservation insights for a selected reporting period.</p>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('staff.reports.index') }}" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-[1fr_1fr_auto] sm:items-end">
        <div><label for="date_from" class="mb-2 block text-sm font-semibold text-slate-700">From</label><input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="min-h-11 w-full rounded-lg border-slate-300"></div>
        <div><label for="date_to" class="mb-2 block text-sm font-semibold text-slate-700">To</label><input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="min-h-11 w-full rounded-lg border-slate-300"><x-input-error :messages="$errors->get('date_to')" class="mt-2" /></div>
        <button class="min-h-11 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700">Generate Report</button>
    </form>

    <div class="mb-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-dashboard-stat title="Reservations" :value="$totalReservations" detail="Selected period" icon="&#128197;" icon-class="bg-blue-50" />
        <x-dashboard-stat title="Approved" :value="$approvedReservations" detail="Approved requests" icon="&#10003;" icon-class="bg-emerald-50" />
        <x-dashboard-stat title="Completed" :value="$completedReservations" detail="Completed events" icon="&#127937;" icon-class="bg-violet-50" />
        <x-dashboard-stat title="Expected Attendees" :value="$totalAttendees" detail="Combined attendance" icon="&#128101;" icon-class="bg-amber-50" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5"><h2 class="font-semibold text-slate-900">Reservations by Status</h2></div>
            <div class="divide-y divide-slate-100 px-6">
                @forelse ($statusCounts as $status => $count)
                    <div class="flex justify-between py-4"><span class="text-sm text-slate-600">{{ ucwords(str_replace('_', ' ', $status)) }}</span><span class="font-semibold">{{ number_format($count) }}</span></div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500">No reservation data for this period.</p>
                @endforelse
            </div>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5"><h2 class="font-semibold text-slate-900">Reservations by Facility</h2></div>
            <div class="divide-y divide-slate-100 px-6">
                @forelse ($facilityCounts as $facility => $count)
                    <div class="flex justify-between py-4"><span class="text-sm text-slate-600">{{ $facility }}</span><span class="font-semibold">{{ number_format($count) }}</span></div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500">No facility data for this period.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-6 flex flex-col justify-end gap-3 sm:flex-row">
        <a href="{{ route('staff.reports.export', array_filter($filters)) }}" class="inline-flex min-h-11 items-center rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">Download Detailed CSV</a>
        <form method="POST" action="{{ route('staff.reports.store') }}" class="flex flex-col gap-3 sm:flex-row">
            @csrf
            <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            <select name="report_type" required class="min-h-11 rounded-lg border-slate-300 text-sm">
                <option value="">Select report type</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="annual">Annual</option>
            </select>
            <button class="min-h-11 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700">Submit to Admin</button>
        </form>
    </div>
</div>
@endsection
