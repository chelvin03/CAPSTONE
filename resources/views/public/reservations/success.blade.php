@extends('layouts.public')
@section('title', 'Reservation Request Submitted Successfully')
@section('body-class', 'reservation-page')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/reservation-wizard.css') }}">
<link rel="stylesheet" href="{{ asset('css/reservation-tracking.css') }}?v={{ filemtime(public_path('css/reservation-tracking.css')) }}">
@endpush
@section('content')
<div class="tracking-page">
    <div class="tracking-shell">
        <div class="tracking-success"><span aria-hidden="true">✓</span><h1>Reservation Request Submitted Successfully</h1><p>Your request is saved. Save your reference number and follow its progress below.</p></div>
        @if(session()->has('reservation_email_sent'))
        <p class="tracking-message" role="status">@if(session('reservation_email_sent'))A confirmation email with your reservation details and tracking link has been sent to {{ $reservation->contact_email }}.@else Your request is saved, but we could not send the confirmation email. Save your reference number below. You do not need to submit again.@endif</p>
        @endif
        @include('public.reservations.partials.live-tracking')
        <div class="tracking-actions">
            <a href="{{ route('reservation.track', ['reference' => $reservation->reference_number]) }}" class="tracking-button tracking-primary">View Reservation<span class="sr-only"> — Track Reservation</span></a>
            <a href="{{ route('home') }}" class="tracking-button">Back to Home</a>
            <a href="{{ route('reservation.create', ['step' => 1]) }}" class="tracking-button">Make Another Reservation</a>
        </div>
    </div>
</div>
<script src="{{ asset('js-reservation-tracking.js') }}?v={{ filemtime(public_path('js-reservation-tracking.js')) }}"></script>
@endsection
