@php($ownsReservation = in_array($reservation->reference_number, session('public_reservation_references', []), true))
@if($reservation->equipment->isNotEmpty())
<section class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-5" aria-labelledby="equipment-updates-heading">
    <h3 id="equipment-updates-heading">Equipment Request Updates</h3>
    @if($ownsReservation)
        @foreach($reservation->notifications as $notification)
            <article class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
                <strong>Equipment Request Update</strong><p>Reservation Reference: {{ $reservation->reference_number }}</p>
                <p class="mt-2 whitespace-pre-line">{{ $notification->data['message'] ?? '' }}</p>
                @if(isset($notification->data['equipment_name']))
                    <p>{{ $notification->data['equipment_name'] }} — Requested: {{ $notification->data['requested'] }} / Available when sent: {{ $notification->data['available'] }} / Approved: {{ $notification->data['quantity'] }}</p>
                @endif
                <time class="text-sm text-slate-500">{{ $notification->created_at->format('M d, Y g:i A') }}</time>
            </article>
        @endforeach
        @foreach($reservation->equipment as $item)
            <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
                <strong>{{ $item->equipment_name }}</strong><p>Requested: {{ $item->pivot->quantity_requested }} / Approved: {{ $item->pivot->quantity_approved ?? 'Pending' }} / Status: {{ ucwords(str_replace('_', ' ', $item->pivot->status)) }}</p>

            </div>
        @endforeach
    @else
        <p>Use the secure link in your equipment update email or the browser where you submitted this reservation to view equipment decisions.</p>
    @endif
</section>
@endif
