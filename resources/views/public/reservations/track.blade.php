@extends('layouts.public')
@section('title', 'Track Reservation')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/reservation-tracking.css') }}?v={{ filemtime(public_path('css/reservation-tracking.css')) }}">
@endpush
@section('content')
<div class="tracking-page"><div class="tracking-shell">
    @if(session('success'))<p class="tracking-message" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="tracking-message" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($reservation && session('reservation_submitted') === $reservation->reference_number)
    <div class="tracking-success" role="status"><span aria-hidden="true">&#10003;</span><h1>Reservation Submitted Successfully</h1><p>Your reservation is saved. Follow its progress below.</p></div>
    @if($reservation->status === 'waiting_list')
    <dialog id="waiting-list-notice" class="waiting-list-notice" aria-labelledby="waiting-list-notice-title">
        <h2 id="waiting-list-notice-title">Your request is on the waiting list</h2>
        <p>Your selected schedule is occupied or within another booking’s cleanup buffer, so your request was automatically placed on the waiting list.</p>
        <p>An administrator will review your request for approval when a suitable slot becomes available. Your booking is not confirmed yet.</p>
        <p><strong>Reference:</strong> {{ $reservation->reference_number }}</p>
        <p>@if(session('reservation_email_sent'))An email with your waiting-list status and tracking link has been sent to {{ $reservation->contact_email }}.@else Your request is saved, but its email could not be sent. Save your reference number and check the status here. You do not need to submit again.@endif</p>
        <form method="dialog"><button class="tracking-button tracking-primary" autofocus>Understood</button></form>
    </dialog>
    <script>document.getElementById('waiting-list-notice').showModal();</script>
    @endif
    @else
    <h1>Track Your Reservation</h1>
    @endif<p>Enter your reservation reference number from your confirmation screen or email.</p>
    <form method="GET" action="{{ route('reservation.track') }}" class="tracking-search"><label class="sr-only" for="tracking-reference">Reservation Reference Number</label><input id="tracking-reference" name="reference" value="{{ $reference }}" required maxlength="50" placeholder="MCST-GYM-YYYYMMDD-XXXXXX"><button class="tracking-button tracking-primary">Track Reservation</button></form>
    @if($reference !== '' && !$reservation)<p class="tracking-message" role="alert">No reservation was found for that reference code.</p>@endif
    @if($reservation)@include('public.reservations.partials.live-tracking')@endif
    @if($reservation && $ownsReservation && $reservation->requestorCancellationAllowed())
    <section class="tracking-cancellation" aria-labelledby="cancellation-heading">
        <h3 id="cancellation-heading">Cancel Reservation</h3>
        <p>You may cancel online at least three days before your scheduled event starts. A reason is required.</p>
        <p class="tracking-cancellation-deadline"><strong>Cancellation deadline:</strong> {{ $reservation->requestorCancellationDeadline()->format('F d, Y g:i A') }} ({{ config('app.timezone') }}).</p>
        <form method="POST" action="{{ route('reservation.cancel', $reservation) }}" onsubmit="return confirm('Cancel this reservation? Your schedule will be released.');">
            @csrf
            <p id="cancellation-help">Cancelling releases your reserved schedule. To reserve again, you will need to submit a new request.</p>
            <label for="cancellation-reason">Cancellation reason <span aria-hidden="true">*</span></label>
            <textarea id="cancellation-reason" name="cancellation_reason" required maxlength="2000" rows="4" placeholder="Tell us why you need to cancel your reservation." aria-describedby="cancellation-help" class="w-full rounded-lg border-slate-300">{{ old('cancellation_reason') }}</textarea>
            <button type="submit" class="tracking-button tracking-danger">Cancel Reservation</button>
        </form>
    </section>
    @elseif($reservation && $ownsReservation && !in_array($reservation->status, ['cancelled', 'rejected', 'completed', 'no_show'], true))
        <p class="tracking-message">Online cancellation is closed. Cancellation requires at least three days before the scheduled event starts. Please contact the administrator for assistance.</p>
    @endif
</div></div>
<script src="{{ asset('js-reservation-tracking.js') }}?v={{ filemtime(public_path('js-reservation-tracking.js')) }}"></script>
@endsection
