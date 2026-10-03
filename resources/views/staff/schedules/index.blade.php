@extends('layouts.app')
@section('title', 'Staff Schedules')
@section('content')
<div class="page-shell">
    <header class="page-header"><div><p class="text-sm font-semibold text-blue-700">Staff Monitoring</p><h1 class="page-title">Schedules</h1><p class="page-description">Read-only approved and completed events. All times use {{ config('app.timezone') }}.</p></div></header>
    @if ($errors->any())<div class="alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <form class="card mb-6 flex flex-wrap items-end gap-3 p-5" method="GET" action="{{ route('staff.schedules.index') }}">
        <label class="text-sm font-semibold">Scheduled date<input class="field mt-2" type="date" name="date" value="{{ $date }}" required></label>
        <button class="btn-primary" type="submit">View schedule</button>
        <a class="btn-secondary" href="{{ route('staff.schedules.index') }}">Today</a>
    </form>
    <section class="card mb-6 p-5" aria-label="Facility availability">
        <h2 class="font-bold">Facility status now</h2>
        <ul class="mt-3 space-y-2 text-sm">@forelse ($facilities as $facility)<li class="flex flex-wrap items-center gap-3"><span>{{ $facility->facility_name }}</span><x-operational-status :status="$facility->status" /><span class="text-slate-600">@if ($facility->status === 'available'){{ $facility->blocked_now ? 'Schedule block in effect now' : ($facility->scheduled_now ? 'In use by schedule — actual presence not tracked' : ($facility->reserved_today ? 'Reserved later today' : 'No remaining approved bookings today')) }}@endif</span></li>@empty<li>No facility records available.</li>@endforelse</ul>
        <p class="mt-3 text-xs text-slate-500">Times without approved/completed bookings or schedule blocks have no recorded booking restriction; facility maintenance and administrator approval still apply.</p>
    </section>
    <section class="card overflow-hidden"><div class="border-b border-slate-100 p-5"><h2 class="font-bold">Schedule for {{ $date }}</h2><p class="mt-1 text-sm text-slate-500">{{ $reservations->total() }} approved or completed events, ordered by start time.</p></div>
        @include('staff.partials.reservations-table', ['rows' => $reservations, 'caption' => 'Selected date schedule', 'emptyMessage' => 'No approved or completed events are scheduled for this date.'])
        @if ($reservations->hasPages())<div class="border-t p-5">{{ $reservations->links() }}</div>@endif
    </section>
    <section class="card mt-6 p-5"><h2 class="font-bold">Blocked times for {{ $date }}</h2><ul class="mt-3 space-y-2 text-sm">@forelse ($blocks as $block)<li><span class="status-badge bg-amber-50 text-amber-800">Unavailable — schedule block</span> {{ $block->facility?->facility_name ?? 'All facilities' }} · {{ $block->start_time ? substr($block->start_time, 0, 5) : 'Start of day' }} to {{ $block->end_time ? substr($block->end_time, 0, 5) : 'End of day' }}</li>@empty<li class="text-slate-500">No schedule blocks recorded for this date.</li>@endforelse</ul>@if ($blocks->hasPages())<div class="mt-4">{{ $blocks->links() }}</div>@endif</section>
</div>
@endsection
