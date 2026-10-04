@extends('layouts.public')

@section('title', 'Track Reservation')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="text-center">
            <p class="text-sm font-semibold text-blue-600">Reservation Tracking</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">Track Your Request</h1>
            <p class="mt-2 text-sm text-slate-500">Enter the unique reference code included in your confirmation email.</p>
        </div>

        <form method="GET" action="{{ route('reservation.track') }}" class="mx-auto mt-6 flex max-w-xl flex-col gap-3 sm:flex-row">
            <input name="reference" value="{{ $reference }}" required maxlength="50" placeholder="MCST-YYYYMMDD-XXXXXX" class="min-h-11 flex-1 rounded-lg border-slate-300 uppercase">
            <button class="min-h-11 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700">Track</button>
        </form>

        @if ($reference !== '' && ! $reservation)
            <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-center text-sm text-red-700">No reservation was found for that reference code.</div>
        @endif

        @if ($reservation)
            <div class="mt-8 border-t border-slate-200 pt-6">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div><p class="text-sm text-slate-500">Reference Code</p><p class="text-xl font-bold text-slate-900">{{ $reservation->reference_number }}</p></div>
                    <span @class(['self-start rounded-full px-3 py-1.5 text-sm font-semibold', 'bg-amber-100 text-amber-800' => in_array($reservation->status, ['new', 'pending', 'validated', 'waiting_list'], true), 'bg-emerald-100 text-emerald-800' => in_array($reservation->status, ['approved', 'completed'], true), 'bg-rose-100 text-rose-800' => in_array($reservation->status, ['rejected', 'cancelled'], true)])>{{ $reservation->status === 'new' ? 'Request Received' : ucwords(str_replace('_', ' ', $reservation->status)) }}</span>
                </div>

                <dl class="mt-6 grid gap-5 rounded-xl bg-slate-50 p-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Event</dt><dd class="mt-1 font-semibold text-slate-900">{{ $reservation->event_name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Facility</dt><dd class="mt-1 font-semibold text-slate-900">{{ $reservation->facility?->facility_name ?? 'Unassigned' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Date</dt><dd class="mt-1 font-semibold text-slate-900">{{ $reservation->reservation_date->format('F d, Y') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Schedule</dt><dd class="mt-1 font-semibold text-slate-900">{{ \Carbon\Carbon::parse($reservation->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($reservation->end_time)->format('g:i A') }}</dd></div>
                </dl>

                <h2 class="mt-7 font-semibold text-slate-900">Status History</h2>
                <div class="mt-3 space-y-3">
                    @foreach ($reservation->statusHistories->sortByDesc('created_at') as $history)
                        <div class="rounded-lg border border-slate-200 p-4"><div class="flex justify-between gap-3"><span class="font-semibold text-slate-800">{{ $history->new_status === 'new' ? 'Request Received' : ucwords(str_replace('_', ' ', $history->new_status)) }}</span><time class="text-xs text-slate-500">{{ $history->created_at->format('M d, Y g:i A') }}</time></div>@if($history->remarks)<p class="mt-1 text-sm text-slate-600">{{ $history->remarks }}</p>@endif</div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
