@extends('layouts.public')
@section('title', 'Track Reservation')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/reservation-tracking.css') }}?v={{ filemtime(public_path('css/reservation-tracking.css')) }}">
@endpush
@section('content')
<div class="tracking-page"><div class="tracking-shell">
    @if($reservation && session('reservation_submitted') === $reservation->reference_number)
    <div class="tracking-success" role="status"><span aria-hidden="true">&#10003;</span><h1>Reservation Submitted Successfully</h1><p>Your reservation is saved. Follow its progress below.</p></div>
    @else
    <h1>Track Your Reservation</h1>
    @endif<p>Enter your reservation reference number from your confirmation screen or email.</p>
    <form method="GET" action="{{ route('reservation.track') }}" class="tracking-search"><label class="sr-only" for="tracking-reference">Reservation Reference Number</label><input id="tracking-reference" name="reference" value="{{ $reference }}" required maxlength="50" placeholder="MCST-GYM-YYYYMMDD-XXXXXX"><button class="tracking-button tracking-primary">Track Reservation</button></form>
    @if($reference !== '' && !$reservation)<p class="tracking-message" role="alert">No reservation was found for that reference code.</p>@endif
    @if($reservation)@include('public.reservations.partials.live-tracking')@endif
    @if($recentReservations->isNotEmpty())
    <section class="tracking-recent"><h2>My Reservation History</h2><p>Requests submitted in this browser. You can also enter a saved reference number above.</p><ul>
        @foreach($recentReservations as $recent)
        <li><a href="{{ route('reservation.track', ['reference' => $recent->reference_number]) }}"><strong>{{ $recent->reference_number }}</strong><span>{{ $recent->event_name }}</span><span>{{ $recent->reservation_date->format('F d, Y') }} · {{ ucwords(str_replace('_', ' ', $recent->status)) }}</span></a></li>
        @endforeach
    </ul></section>
    @endif
    <div class="tracking-actions"><a href="{{ route('home') }}" class="tracking-button">Back to Home</a><a href="{{ route('reservation.create', ['step' => 1]) }}" class="tracking-button">Make Another Reservation</a></div>
</div></div>
<script src="{{ asset('js-reservation-tracking.js') }}?v={{ filemtime(public_path('js-reservation-tracking.js')) }}"></script>
@endsection
