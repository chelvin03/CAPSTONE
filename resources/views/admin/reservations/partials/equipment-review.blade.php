<section class="equipment-review rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" style="font-family: Poppins, sans-serif;">
    <h2 class="mb-3 text-lg font-bold" style="color: #1E3A8A;">Equipment Request</h2>
    <p class="mb-4 text-sm text-slate-600">Equipment requests are subject to availability and approval by the Gym Administrator.</p>
    @if ($reservation->requested_equipment)
        <p class="mb-4 text-sm">Additional item request: {{ $reservation->requested_equipment }} ({{ $reservation->requested_equipment_quantity }}). Inventory quantities must be reviewed separately.</p>
    @endif
    <div class="overflow-x-auto">
    <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50"><tr>@foreach(['Equipment','Requested','Available','Approved','Status','Action'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($reservation->equipment as $item)
            @php
                $available = app(\App\Services\EquipmentRequestService::class)->available($item, $reservation);
                $status = $item->pivot->status;
                $canReview = $reservation->reservation_type === 'internal' && !in_array($reservation->status, ['cancelled', 'rejected', 'completed'], true);
                $approvable = min($available, $item->pivot->quantity_requested);
            @endphp
            <tr class="border-t border-slate-200 align-top">
                <td class="p-3 font-semibold">{{ $item->equipment_name }}</td>
                <td class="p-3">{{ $item->pivot->quantity_requested }}</td>
                <td class="p-3">{{ $available }}</td>
                <td class="p-3">{{ $item->pivot->quantity_approved ?? 'Pending' }}</td>
                <td class="p-3"><span @class(['inline-flex rounded-full px-3 py-1 text-xs font-semibold', 'bg-emerald-100 text-emerald-800' => in_array($status,['approved','available','accepted_by_requestor']), 'bg-amber-100 text-amber-800' => $status === 'partially_available', 'bg-red-100 text-red-800' => in_array($status,['unavailable','rejected','declined_by_requestor']), 'bg-blue-50 text-blue-800' => $status === 'pending'])>{{ ucwords(str_replace('_',' ', $status)) }}</span></td>
                <td class="p-3">
                @if($canReview)
                    <form method="POST" action="{{ route('admin.reservations.equipment.review', [$reservation, $item]) }}" class="flex flex-wrap gap-2">
                        @csrf<input type="hidden" name="action" value="approve">
                        <label class="sr-only" for="approve-{{ $item->id }}">Quantity approved for {{ $item->equipment_name }}</label>
                        <input id="approve-{{ $item->id }}" name="quantity_approved" type="number" min="1" max="{{ $approvable }}" step="1" value="{{ $approvable }}" required class="w-24 rounded-lg border-slate-300">
                        <button class="btn-primary !px-3 !py-2" @disabled($available < 1)>Approve Equipment</button>
                    </form>
                    <form method="POST" action="{{ route('admin.reservations.equipment.review', [$reservation, $item]) }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn-secondary" onclick="return confirm('Reject this equipment request?')">Reject Equipment</button>
                    </form>
                @else
                    <span class="text-slate-500">Review unavailable</span>
                @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-3 text-slate-500">No inventory equipment requested.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <h3 class="mt-5 font-semibold">Equipment Decision History</h3>
    @forelse($reservation->notifications as $notification)
        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm"><p>{{ $notification->data['message'] ?? '' }}</p><small>{{ $notification->created_at->format('M d, Y g:i A') }}</small></div>
    @empty
        <p class="mt-2 text-sm text-slate-500">No equipment decisions yet.</p>
    @endforelse
</section>
