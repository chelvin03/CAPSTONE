@extends('layouts.app')
@section('title', 'Waiting List | MCST Gym')
@section('content')
<div class="page-shell">
    <div class="page-header">
        <div><p class="text-sm font-semibold text-blue-600">MCST GYMNASIUM</p><h1 class="page-title">Waiting List</h1><p class="page-description">View and manage gymnasium requests waiting for an available schedule.</p></div>
        <a href="{{ route('admin.reservations.index') }}" class="btn-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Reservations</a>
    </div>
    @if($errors->any())<div class="alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($stats as $label => $count)
        @php
            $colors = ['bg-blue-50 text-blue-700', 'bg-emerald-50 text-emerald-700', 'bg-violet-50 text-violet-700', 'bg-amber-50 text-amber-700'];
            $icons = ['people-fill', 'clock', 'calendar-week', 'calendar-month'];
        @endphp
        <div class="flex items-center gap-5 rounded-xl p-5 {{ $colors[$loop->index] }}">
            <i class="bi bi-{{ $icons[$loop->index] }} text-3xl" aria-hidden="true"></i>
            <div><p class="text-sm font-semibold">{{ $label }}</p><p class="my-1 text-3xl font-bold">{{ $count }}</p><p class="text-xs">{{ $loop->first ? 'All waiting requests' : ($stats['Total on Waiting List'] ? round($count / $stats['Total on Waiting List'] * 100, 1) : 0).'% of total' }}</p></div>
        </div>
        @endforeach
    </div>
    <div class="card p-4 sm:p-5">
        <form method="GET" action="{{ route('admin.reservations.waiting-list') }}" class="mb-5 flex flex-wrap items-end gap-3" role="search">
            <label class="min-w-48 flex-1 text-sm">Search<input class="field mt-1 w-full" name="search" value="{{ request('search') }}" placeholder="Name, event, contact number or notes"></label>
            <label class="text-sm">Event Type<select class="field mt-1 block" name="event_type"><option value="">All event types</option>@foreach($eventTypes as $type)<option @selected(request('event_type') === $type) value="{{ $type }}">{{ $type }}</option>@endforeach</select></label>
            <label class="text-sm">Preferred Date<input class="field mt-1 block" type="date" name="date" value="{{ request('date') }}"></label>
            <button class="btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Filter</button>
            <a href="{{ route('admin.reservations.waiting-list') }}" class="btn-secondary">Clear</a>
            <button class="btn-secondary" name="export" value="csv"><i class="bi bi-download" aria-hidden="true"></i> Export</button>
        </form>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-blue-50 text-left text-blue-900"><tr>@foreach(['#', 'Name', 'Contact Number', 'Event Type', 'Preferred Date', 'Preferred Time', 'Joined On', 'Status', 'Position', 'Actions'] as $heading)<th class="whitespace-nowrap px-3 py-3">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse($reservations as $reservation)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-3">{{ $reservations->firstItem() + $loop->index }}</td>
                        <td class="px-3 py-3"><p class="font-medium">{{ $reservation->contact_person }}</p><p class="text-xs text-slate-500">{{ $reservation->reference_number }}</p></td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $reservation->contact_number }}</td>
                        <td class="px-3 py-3">{{ $reservation->event_type ?: $reservation->event_name }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $reservation->reservation_date->format('M d, Y') }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ substr($reservation->start_time, 0, 5) }} – {{ substr($reservation->end_time, 0, 5) }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $reservation->created_at->format('M d, Y g:i A') }}</td>
                        <td class="px-3 py-3"><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">WAITING</span></td>
                        <td class="px-3 py-3"><span class="rounded bg-blue-50 px-3 py-1 font-semibold text-blue-700">{{ $reservation->queue_position }}</span></td>
                        <td class="px-3 py-3"><a class="btn-secondary whitespace-nowrap" href="{{ route('admin.reservations.show', $reservation) }}"><i class="bi bi-eye" aria-hidden="true"></i> Review / Offer</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-12 text-center text-slate-500">No waiting-list requests found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $reservations->links() }}</div>
    </div>
    <div class="mt-4 rounded-xl border border-blue-100 bg-blue-50 p-5 text-sm text-blue-800">
        <p class="mb-2 font-semibold"><i class="bi bi-info-circle" aria-hidden="true"></i> Important Notes</p>
        <ul class="list-disc space-y-1 pl-5"><li>Positions follow request creation order for the same facility, date and exact time slot. Filters do not change positions.</li><li>Review a request to check requirements, offer a schedule, approve it, or remove it by cancelling with a recorded reason.</li><li>Availability must be checked again at approval. A waiting-list position does not confirm a booking.</li></ul>
    </div>
</div>
@endsection
