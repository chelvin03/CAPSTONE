@extends('layouts.app')

@section('title', 'Reservation Details')

@section('content')
<link rel="stylesheet" href="{{ asset('css/schedule-impact.css') }}?v={{ filemtime(public_path('css/schedule-impact.css')) }}"><script src="{{ asset('js-schedule-impact.js') }}?v={{ filemtime(public_path('js-schedule-impact.js')) }}"></script>
<link rel="stylesheet" href="{{ asset('css/reservation-details.css') }}?v={{ filemtime(public_path('css/reservation-details.css')) }}">
<div class="reservation-details mx-auto max-w-6xl" x-data="{ detailTab: @js(old('outcome') !== null || old('actual_attendees') !== null ? 'attendance' : (old('quantity_approved') !== null || old('quantity_offered') !== null || old('issued') !== null ? 'equipment' : (old('action') === 'notes' || old('cancellation_reason') !== null ? 'history' : 'overview'))) }" x-init="const hash = window.location.hash.slice(1); if (hash.includes('equipment')) detailTab = 'equipment'; else if (hash === 'event-outcome' || hash === 'attendance') detailTab = 'attendance'; else if (hash === 'history') detailTab = 'history';">
    @if($reservation->status === 'cancelled')
        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="released-waiting-heading">
            <h2 id="released-waiting-heading" class="font-semibold">Waiting-list review for released schedule</h2>
            <p>These requests currently fit the released schedule. Review their requirements before approval. Availability is checked again when you approve.</p>
            @forelse($releasedWaitingList as $waiting)
                <p class="mt-2"><a class="text-blue-600 underline" href="{{ route('admin.reservations.show', $waiting) }}">Review {{ $waiting->reference_number }}</a> — {{ substr($waiting->start_time, 0, 5) }} to {{ substr($waiting->end_time, 0, 5) }}</p>
            @empty
                <p class="mt-2">No eligible waiting-list requests currently fit this released schedule.</p>
            @endforelse
        </section>
    @endif
    @foreach($reservation->notifications->where('type', \App\Notifications\ReservationStatusUpdate::class) as $notice)
        @if(($notice->data['mail_status'] ?? '') === 'failed')
            <p class="mb-4 rounded-lg bg-red-50 p-3 text-red-700" role="alert">The {{ $notice->data['status'] }} update was saved, but its email could not be sent. Contact the requestor and check mail configuration.</p>
        @endif
    @endforeach

    <header class="detail-header">
        <div><a class="detail-back" href="{{ route('admin.reservations.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Reservations</a><h1>Reservation Details</h1><p>View all information about this reservation request and take action.</p></div>
        <div class="detail-reference"><div>Reservation Number: <strong>{{ $reservation->reference_number }}</strong><span class="detail-header-badge detail-badge-{{ $reservation->status }}">{{ ucwords(str_replace('_', ' ', $reservation->status)) }}</span></div><small>Date Requested: {{ $reservation->created_at->format('M d, Y g:i A') }}</small></div>
    </header>
    @if(in_array($reservation->status, ['approved', 'completed'], true))
        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4"><p class="font-semibold text-green-800">Approved / For AR — staff preparation required</p><a class="mt-2 inline-block font-semibold text-blue-700 underline" href="{{ route('admin.reservations.receipt', $reservation) }}" target="_blank" rel="noopener"><i class="bi bi-printer" aria-hidden="true"></i> View AR Progress</a></div>
    @endif
    @if (session('success'))
        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
            <ul class="list-inside list-disc text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <nav class="detail-statuses detail-statuses-top" aria-label="Reservation status overview">
        @foreach(['new' => ['New', 'Request submitted'], 'validated' => ['Validated', 'Documents and details verified'], 'approved' => ['Approved', 'Slot confirmed'], 'waiting_list' => ['Waiting List', 'No available slot'], 'rejected' => ['Rejected', 'Request declined'], 'completed' => ['Completed', 'Event completed']] as $statusKey => [$statusLabel, $statusDescription])
        @php
            $statusDate = $statusKey === 'new' ? $reservation->created_at : $reservation->statusHistories->where('new_status', $statusKey)->sortByDesc('created_at')->first()?->created_at;
        @endphp
        <div class="detail-status {{ $reservation->status === $statusKey || ($statusKey === 'new' && $reservation->status === 'pending') ? 'is-current' : '' }}" @if($reservation->status === $statusKey) aria-current="step" @endif><span class="detail-status-number">{{ $loop->iteration }}</span><div><strong>{{ $statusLabel }}</strong><small>{{ $statusDescription }}</small><small>{{ $statusDate?->format('M j, Y g:i A') ?? '-' }}</small></div></div>
        @endforeach
    </nav>

@php
    $startMinutes = (int) substr($reservation->start_time, 0, 2) * 60 + (int) substr($reservation->start_time, 3, 2);
    $endMinutes = (int) substr($reservation->end_time, 0, 2) * 60 + (int) substr($reservation->end_time, 3, 2);
    $durationMinutes = max(0, ($endMinutes === 0 ? 1440 : $endMinutes) - $startMinutes);
    $durationLabel = intdiv($durationMinutes, 60).' hours '.($durationMinutes % 60).' minutes';
    $startLabel = \Carbon\Carbon::parse($reservation->start_time)->format('g:i A');
    $endLabel = in_array(substr($reservation->end_time, 0, 5), ['00:00', '24:00']) ? '12:00 AM' : \Carbon\Carbon::parse($reservation->end_time)->format('g:i A');
    $capacityUsage = $reservation->expected_attendees / \App\Support\GymCapacity::MAX_ATTENDEES * 100;
@endphp
    <nav class="detail-tabs" aria-label="Reservation detail views">
        @foreach(['overview' => ['Overview', 'bi-journal-check'], 'equipment' => ['Equipment', 'bi-calendar2-range'], 'attendance' => ['Attendance & Outcome', 'bi-people'], 'history' => ['History & Notes', 'bi-file-earmark-text']] as $tab => [$caption, $icon])
            <button type="button" @click="detailTab = @js($tab); window.history.replaceState(null, '', '#' + detailTab)" :aria-pressed="detailTab === @js($tab)" :class="{ 'is-active': detailTab === @js($tab) }"><i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $caption }}</button>
        @endforeach
    </nav>
    <div class="reservation-detail-grid grid gap-6 lg:grid-cols-3">

        <div class="detail-main"><div class="detail-information detail-overview-layout" x-show="detailTab === 'overview'">


            @include('admin.reservations.partials.detail-overview')

            @include('admin.reservations.partials.detail-documents')
        </div><div class="detail-tab-content" x-show="detailTab === 'equipment'" x-cloak>
            @include('admin.reservations.partials.equipment-review')
            @include('reservations.equipment-returns')
        </div><div class="detail-tab-content" x-show="detailTab === 'attendance'" x-cloak>
            @include('reservations.event-outcome')
        </div><div x-show="detailTab === 'history'" x-cloak>

    <details class="detail-more" x-show="detailTab === 'history'" x-cloak open><summary>Cancellation &amp; reservation records</summary><div class="detail-more-content">    <section class="detail-notes detail-card"><header class="detail-card-heading"><h2><i class="bi bi-file-earmark-text-fill" aria-hidden="true"></i> Additional Information &amp; Notes</h2></header>
        @if($reservation->additional_notes)<p class="detail-public-notes">{{ $reservation->additional_notes }}</p>@endif
        <form method="POST" action="{{ route('admin.reservations.review', $reservation) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="notes"><label class="sr-only" for="detail-internal-notes">Internal reservation notes</label><textarea id="detail-internal-notes" name="admin_notes" rows="3" placeholder="Add internal notes about this reservation (optional)...">{{ old('admin_notes', $reservation->admin_notes) }}</textarea><button class="btn-primary" type="submit">Save Notes</button></form>
    </section>
            @if (in_array($reservation->status, ['new', 'pending', 'validated', 'waiting_list', 'approved', 'pencil'], true))
                <section class="rounded-2xl border border-red-200 bg-red-50 p-6">
                    <h2 class="mb-3 text-lg font-bold text-red-800">
                        Cancel Reservation
                    </h2>

                    <form
                        method="POST"
                        action="{{ route('admin.reservations.cancel', $reservation) }}"
                    >
                        @csrf
                        @method('PATCH')

                        <textarea
                            name="cancellation_reason"
                            rows="3"
                            required
                            placeholder="Enter cancellation reason"
                            class="mb-3 w-full rounded-lg border border-red-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-red-100"
                        ></textarea>

                        <button
                            type="submit"
                            class="w-full rounded-lg bg-red-700 px-4 py-3 text-sm font-semibold text-white hover:bg-red-800"
                        >
                            Cancel Reservation
                        </button>
                    </form>
                </section>
            @endif

            <section class="detail-agreement rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="gym-agreement-heading">
                <h2 id="gym-agreement-heading" class="mb-5 text-lg font-bold" style="color: #1E3A8A">Gymnasium Use Agreement</h2>
                <dl class="grid gap-5 text-sm sm:grid-cols-2">
                    <div><dt class="font-semibold text-slate-500">Agreement Status</dt><dd class="mt-2">
                        @if ($reservation->agreement_accepted)
                            <span class="inline-flex rounded-full px-3 py-1 font-semibold text-white" style="background-color: #10B981">Accepted</span>
                        @else
                            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-600">Not recorded</span>
                        @endif
                    </dd></div>
                    <div><dt class="font-semibold text-slate-500">Date Accepted</dt><dd class="mt-2 text-slate-700">{{ $reservation->agreement_accepted_at?->format('F d, Y g:i A') ?? 'Not recorded' }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Requestor Name</dt><dd class="mt-2 break-words text-slate-700">{{ $reservation->contact_person }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Reservation Reference Number</dt><dd class="mt-2 break-words text-slate-700">{{ $reservation->reference_number }}</dd></div>
                </dl>
            </section>


</div></details>
        </div>
        </div>

        <div class="detail-actions space-y-6">



            @include('admin.reservations.partials.detail-actions')

        </div>

    </div>





    @include('admin.reservations.partials.detail-scheduling')
</div>
@endsection
