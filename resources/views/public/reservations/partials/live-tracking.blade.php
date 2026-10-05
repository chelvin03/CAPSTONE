<div x-data="reservationTracker(@js(route('reservation.track', ['reference' => $reservation->reference_number])))">
    <div x-ref="trackingPanel">@include('public.reservations.partials.tracking')</div>
    <p class="tracking-refresh" role="status" x-text="refreshMessage">Status updates automatically every 5 seconds.</p>
</div>
