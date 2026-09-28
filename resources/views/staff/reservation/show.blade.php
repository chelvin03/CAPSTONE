@extends('layouts.app')
@section('title', 'Reservation Details')
@section('content')
<div class="page-shell">
    <header class="page-header"><div><a class="text-sm font-semibold text-blue-700 underline" href="{{ route('staff.reservations.index') }}">Back to Reservations</a><h1 class="page-title mt-3">Reservation Details</h1><p class="page-description">Read-only operational information.</p></div></header>
    <section class="card p-6"><h2 class="text-lg font-bold">{{ $reservation->event_name }}</h2><dl class="mt-5 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (['Reference Number' => $reservation->reference_number, 'Event Type' => $reservation->event_type ?: 'Not specified', 'Facility' => $reservation->facility?->facility_name ?? 'Not assigned', 'Scheduled Date' => $reservation->reservation_date->format('F d, Y'), 'Start–End Time' => substr($reservation->start_time, 0, 5).'–'.substr($reservation->end_time, 0, 5), 'Expected Attendees' => number_format($reservation->expected_attendees)] as $heading => $value)
            <div><dt class="text-sm font-semibold text-slate-500">{{ $heading }}</dt><dd class="mt-2 text-sm">{{ $value }}</dd></div>
        @endforeach
        <div><dt class="text-sm font-semibold text-slate-500">Status</dt><dd class="mt-2"><x-operational-status :status="$reservation->status" /></dd></div>
    </dl></section>
</div>
@endsection
