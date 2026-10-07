@php($ownsReservation = in_array($reservation->reference_number, session('public_reservation_references', []), true))
@if($reservation->equipment->isNotEmpty())
<section class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-5" aria-labelledby="equipment-updates-heading">
    <h3 id="equipment-updates-heading">Messages / Equipment Updates</h3>
    @if($ownsReservation)
        @foreach($reservation->notifications as $notification)
            <article class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
                <strong>Equipment Request Update</strong><p>Reservation Reference: {{ $reservation->reference_number }}</p>
                <p class="mt-2 whitespace-pre-line">{{ $notification->data['message'] ?? '' }}</p>
                @if(isset($notification->data['equipment_name']))
                    <p>{{ $notification->data['equipment_name'] }} — Requested: {{ $notification->data['requested'] }} / Available when sent: {{ $notification->data['available'] }} / Approved/Offered: {{ $notification->data['quantity'] }}</p>
                @endif
                <time class="text-sm text-slate-500">{{ $notification->created_at->format('M d, Y g:i A') }}</time>
            </article>
        @endforeach
        @foreach($reservation->equipment as $item)
            <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
                <strong>{{ $item->equipment_name }}</strong><p>Requested: {{ $item->pivot->quantity_requested }} / Approved: {{ $item->pivot->quantity_approved ?? 'Pending' }} / Status: {{ ucwords(str_replace('_', ' ', $item->pivot->status)) }}</p>
                @if($item->pivot->requires_response && !$item->pivot->requestor_response && $item->pivot->latest_offer_id && in_array($item->pivot->status, ['available','partially_available']) && !in_array($reservation->status, ['cancelled','rejected','completed']))
                    <p class="mt-2">Admin offered: {{ $item->pivot->quantity_offered }} {{ $item->equipment_name }}. Acceptance does not guarantee availability until final administrator approval.</p>
                    <form method="POST" action="{{ route('reservation.equipment.respond', [$reservation, $item]) }}" class="mt-3 flex flex-wrap gap-3">
                        @csrf<input type="hidden" name="offer_id" value="{{ $item->pivot->latest_offer_id }}">
                        <button name="response" value="accepted" class="tracking-button tracking-primary">Accept {{ $item->pivot->quantity_offered }} {{ $item->equipment_name }}</button>
                        <button name="response" value="declined" class="tracking-button">Decline Equipment Request</button>
                    </form>
                @endif
            </div>
        @endforeach
    @else
        <p>Use the secure link in your equipment update email or the browser where you submitted this reservation to view and respond to messages.</p>
    @endif
</section>
@endif
