@extends('layouts.app')
@section('title', 'Staff Dashboard')
@section('content')
<div class="page-shell">
    <header class="card mb-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:p-8">
        <img src="{{ asset('images/mcst-logo.png') }}" alt="MCST Logo" class="h-16 w-16 shrink-0 object-contain">
        <div><p class="text-sm font-semibold text-blue-700">MCST Gymnasium</p><h1 class="page-title mt-1">Staff Dashboard</h1><p class="mt-2 font-semibold">Welcome, {{ auth()->user()->full_name }}.</p><p class="page-description">Monitor today’s schedules, upcoming reservations, facility availability, and equipment status.</p></div>
    </header>
    <aside class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
        <p><strong>Staff access is view-only.</strong> Staff members can monitor reservations, schedules, facility availability, and equipment status. Approval, rejection, cancellation, rescheduling, record modification, report generation, and data export are restricted to the Gym Administrator.</p>
    </aside>
    <p class="mb-4 text-sm text-slate-600">{{ $now->format('l, F d, Y') }} · Updated {{ $now->format('g:i A') }} ({{ config('app.timezone') }})</p>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Operational summary">
        <a class="card p-5" href="{{ route('staff.schedules.index', ['date' => $today]) }}"><i class="bi bi-calendar-event text-2xl text-blue-700" aria-hidden="true"></i><h2 class="mt-3 text-sm font-semibold text-slate-600">Today’s Events</h2><p class="mt-2 text-3xl font-bold">{{ $todayCount }}</p><p class="mt-2 text-xs text-slate-500">Approved and completed events today</p></a>
        <a class="card p-5" href="{{ route('staff.reservations.index', ['status' => 'approved']) }}"><i class="bi bi-calendar-week text-2xl text-blue-700" aria-hidden="true"></i><h2 class="mt-3 text-sm font-semibold text-slate-600">Upcoming Reservations</h2><p class="mt-2 text-3xl font-bold">{{ $upcomingCount }}</p><p class="mt-2 text-xs text-slate-500">Approved bookings after today through {{ $sevenDaysThrough }}</p></a>
        <a class="card p-5" href="{{ route('staff.facilities.index') }}"><i class="bi bi-building text-2xl text-blue-700" aria-hidden="true"></i><h2 class="mt-3 text-sm font-semibold text-slate-600">Facility Status</h2><ul class="mt-2 space-y-3 text-sm">@forelse ($facilities as $facility)<li><span class="mb-1 block font-semibold">{{ $facility->facility_name }}</span><x-operational-status :status="$facility->status" />@if ($facility->status === 'available')<span class="mt-1 block text-xs text-slate-600">{{ $facility->blocked_now ? 'Schedule block in effect' : ($facility->scheduled_now ? 'In use by schedule' : ($facility->reserved_today ? 'Reserved later today' : 'No remaining approved bookings today')) }}</span>@endif</li>@empty<li>No facility records available.</li>@endforelse</ul><p class="mt-2 text-xs text-slate-500">Stored status; actual presence is not tracked.</p></a>
        <a class="card p-5" href="{{ route('staff.equipment.index') }}"><i class="bi bi-tools text-2xl text-blue-700" aria-hidden="true"></i><h2 class="mt-3 text-sm font-semibold text-slate-600">Equipment Status</h2><p class="mt-2 font-bold">{{ $equipmentCounts->get('available', 0) }} available</p><p class="mt-1 text-sm">{{ $equipmentCounts->get('maintenance', 0) }} under maintenance · {{ $equipmentCounts->get('unavailable', 0) }} unavailable</p><p class="mt-2 text-xs text-slate-500">Counts of equipment records, not individual units or live stock. {{ $equipmentCounts->sum() }} total records.</p></a>
    </section>
    <section class="card mt-6 p-5" aria-labelledby="attention-title"><h2 id="attention-title" class="font-bold">Attention Needed</h2>
        <ul class="mt-3 space-y-3 text-sm">@forelse ($notices as $notice)<li class="flex items-start gap-3"><i class="bi bi-exclamation-circle text-amber-700" aria-hidden="true"></i><a class="text-slate-700 underline decoration-slate-300 underline-offset-4" href="{{ $notice['url'] }}">{{ $notice['text'] }}</a></li>@empty<li class="text-slate-500">No operational notices at this time.</li>@endforelse</ul>
        @if ($notices->isNotEmpty())<p class="mt-3 text-xs text-slate-500">Attendance notices cover today and the next seven days; changes and cancellations cover the last seven days. Up to five notices of each reservation type.</p>@endif
    </section>
    <section class="card mt-6 overflow-hidden"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"><div><h2 class="font-bold">Today’s Gymnasium Schedule</h2><p class="mt-1 text-sm text-slate-500">{{ $todayCount }} approved or completed events · Ordered by start time</p></div><a class="font-semibold text-blue-700 underline" href="{{ route('staff.schedules.index', ['date' => $today]) }}">View Schedule</a></div>
        @include('staff.partials.reservations-table', ['rows' => $todayReservations, 'caption' => 'Today’s Gymnasium Schedule', 'emptyMessage' => 'No gymnasium events are scheduled for today.'])
        @if ($todayReservations->hasPages())<div class="border-t p-5">{{ $todayReservations->links() }}</div>@endif
    </section>
    <section class="card mt-6 overflow-hidden"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"><div><h2 class="font-bold">Upcoming Approved Reservations</h2><p class="mt-1 text-sm text-slate-500">Next five after today, ordered by scheduled date and start time. This list may extend beyond seven days.</p></div><a class="font-semibold text-blue-700 underline" href="{{ route('staff.reservations.index') }}">View All Reservations</a></div>
        @include('staff.partials.reservations-table', ['rows' => $upcomingReservations, 'caption' => 'Upcoming Approved Reservations', 'emptyMessage' => 'No upcoming approved reservations.'])
    </section>
</div>
@endsection
