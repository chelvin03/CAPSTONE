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
    <section class="card mt-6 p-6"><h2 class="text-lg font-bold">Equipment Request</h2>
        <div class="overflow-x-auto"><table class="mt-4 min-w-full text-left text-sm"><thead><tr><th class="p-3">Equipment</th><th class="p-3">Requested</th><th class="p-3">Approved</th><th class="p-3">Status</th><th class="p-3">Admin Reply / Remarks</th></tr></thead><tbody>
        @forelse($reservation->equipment as $item)<tr class="border-t"><td class="p-3">{{ $item->equipment_name }}</td><td class="p-3">{{ $item->pivot->quantity_requested }}</td><td class="p-3">{{ $item->pivot->quantity_approved ?? 'Pending' }}</td><td class="p-3">{{ ucwords(str_replace('_', ' ', $item->pivot->status)) }}</td><td class="p-3">{{ $item->pivot->remarks ?? 'No reply' }}</td></tr>@empty<tr><td colspan="5" class="p-3">No equipment requested.</td></tr>@endforelse
        </tbody></table></div>
    </section>
@endsection
