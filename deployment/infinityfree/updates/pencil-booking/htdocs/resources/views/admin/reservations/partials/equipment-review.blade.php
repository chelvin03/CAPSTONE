<section class="equipment-review rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" style="font-family: Poppins, sans-serif;">
    <h2 class="mb-3 text-lg font-bold" style="color: #1E3A8A;">Equipment Request</h2>
    <p class="mb-4 text-sm text-slate-600">Equipment requests are subject to availability and approval by the Gym Administrator. Offers do not reserve stock until final approval and reservation approval.</p>
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
                $approvable = min($available, $item->pivot->quantity_requested, $item->pivot->requires_response && $item->pivot->requestor_response === 'accepted' ? $item->pivot->quantity_offered : $item->pivot->quantity_requested);
            @endphp
            <tr class="border-t border-slate-200 align-top">
                <td class="p-3 font-semibold">{{ $item->equipment_name }}</td>
                <td class="p-3">{{ $item->pivot->quantity_requested }}</td>
                <td class="p-3">{{ $available }}</td>
                <td class="p-3">{{ $item->pivot->quantity_approved ?? 'Pending' }}@if($item->pivot->quantity_offered !== null)<small class="block">Offered: {{ $item->pivot->quantity_offered }}</small>@endif</td>
                <td class="p-3"><span @class(['inline-flex rounded-full px-3 py-1 text-xs font-semibold', 'bg-emerald-100 text-emerald-800' => in_array($status,['approved','available','accepted_by_requestor']), 'bg-amber-100 text-amber-800' => $status === 'partially_available', 'bg-red-100 text-red-800' => in_array($status,['unavailable','rejected','declined_by_requestor']), 'bg-blue-50 text-blue-800' => $status === 'pending'])>{{ ucwords(str_replace('_',' ', $status)) }}</span></td>
                <td class="p-3">
                @if($canReview)
                    <form method="POST" action="{{ route('admin.reservations.equipment.review', [$reservation, $item]) }}" class="flex flex-wrap gap-2">
                        @csrf<input type="hidden" name="action" value="approve">
                        <label class="sr-only" for="approve-{{ $item->id }}">Quantity approved for {{ $item->equipment_name }}</label>
                        <input id="approve-{{ $item->id }}" name="quantity_approved" type="number" min="1" max="{{ $approvable }}" step="1" value="{{ $approvable }}" required class="w-24 rounded-lg border-slate-300">
                        <button class="btn-primary !px-3 !py-2" @disabled($available < 1 || ($item->pivot->requires_response && $item->pivot->requestor_response !== 'accepted'))>Approve Equipment</button>
                    </form>
                    <details class="mt-3"><summary class="cursor-pointer font-semibold text-blue-700">Reply / Reject</summary>
                        <form method="POST" action="{{ route('admin.reservations.equipment.review', [$reservation, $item]) }}" class="mt-3 space-y-3" x-data="{ sendOpen: false }">
                            @csrf
                            <label class="block">Offered quantity<input name="quantity_offered" type="number" min="0" max="{{ min($available, $item->pivot->quantity_requested) }}" step="1" value="{{ min($available, $item->pivot->quantity_requested) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
                            <label class="block">Admin Reply / Remarks<textarea name="remarks" rows="3" maxlength="5000" required class="mt-1 w-full rounded-lg border-slate-300">{{ old('remarks') }}</textarea></label>
                            <label class="flex gap-2"><input name="requires_response" type="checkbox" value="1"> Require requestor confirmation</label>
                            <button type="button" @click="if ($el.form.reportValidity()) sendOpen = true" class="btn-primary">Send Reply to Requestor</button>
                            <button name="action" value="reject" class="btn-secondary" onclick="return confirm('Reject this equipment request?')">Reject Equipment</button>
                            <div x-show="sendOpen" x-cloak role="dialog" aria-modal="true" aria-label="Confirm equipment reply" @keydown.escape.window="sendOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
                                <div class="w-full max-w-md rounded-xl bg-white p-6"><p class="mb-5 font-semibold">Send this message to the requestor?</p><div class="flex justify-end gap-3"><button type="button" @click="sendOpen = false" class="btn-secondary">Cancel</button><button name="action" value="reply" class="btn-primary">Send Reply</button></div></div>
                            </div>
                        </form>
                    </details>
                @else
                    <span class="text-slate-500">Review unavailable</span>
                @endif
                </td>
            </tr>
            @if($item->pivot->remarks)<tr><td colspan="6" class="break-words bg-slate-50 p-3">Admin reply: {{ $item->pivot->remarks }}@if($item->pivot->reply_sent_at)<small class="block">Sent: {{ $item->pivot->reply_sent_at }}</small>@endif</td></tr>@endif
        @empty
            <tr><td colspan="6" class="p-3 text-slate-500">No inventory equipment requested.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <h3 class="mt-5 font-semibold">Messages / Equipment Updates</h3>
    @forelse($reservation->notifications as $notification)
        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm"><p>{{ $notification->data['message'] ?? '' }}</p><small>{{ $notification->created_at->format('M d, Y g:i A') }}</small></div>
    @empty
        <p class="mt-2 text-sm text-slate-500">No equipment messages yet.</p>
    @endforelse
</section>
