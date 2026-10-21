@php
    $returnsEnded = !app(\App\Services\EventOutcomeService::class)->endAt($reservation)->isFuture();
    $returnRecords = \App\Models\EquipmentReturn::where('reservation_id', $reservation->id)->with('recorder')->get()->keyBy('equipment_id');
    $returnsAdmin = auth()->user()?->role === 'admin';
@endphp
@if($reservation->equipment->where('pivot.quantity_approved', '>', 0)->isNotEmpty())
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6" id="equipment-returns">
    <h2 class="text-lg font-bold">Equipment returns and incidents</h2>
    <p class="my-3 text-sm">Record actual issued quantities after the event. Good returns, damaged and missing must account for every issued item. Recording completion alone does not mark equipment returned. Only administrators may correct records or restore recovered/repaired stock.</p>
    @foreach($reservation->equipment->where('pivot.quantity_approved', '>', 0) as $returnItem)
        @php($returnRecord = $returnRecords->get($returnItem->id))
        <details class="my-3 rounded border border-slate-200 p-3"><summary class="font-semibold">{{ $returnItem->equipment_name }} — {{ $returnRecord ? 'Return recorded' : 'Not recorded' }} (approved: {{ $returnItem->pivot->quantity_approved }})</summary>
            @if($returnRecord)<p class="my-3 text-sm">Issued {{ $returnRecord->issued }}; good {{ $returnRecord->returned_good }}; damaged {{ $returnRecord->damaged }}; missing {{ $returnRecord->missing }}; restored {{ $returnRecord->restored }}. {{ $returnRecord->remarks }} Recorded by {{ $returnRecord->recorder?->full_name }} on {{ $returnRecord->updated_at->format('M j, Y g:i A') }}; revision {{ $returnRecord->revision }}.</p>@endif
            @if($returnsEnded && (($returnRecord && $returnsAdmin) || (!$returnRecord && in_array($reservation->status, ['approved', 'completed', 'no_show'], true))))
                <form method="POST" action="{{ route(($returnsAdmin ? 'admin' : 'staff').'.reservations.equipment.return', [$reservation, $returnItem]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
                    <input type="hidden" name="revision" value="{{ $returnRecord?->revision ?? 0 }}">
                    @foreach(['issued' => 'Actually issued', 'returned_good' => 'Returned in good condition', 'damaged' => 'Returned damaged', 'missing' => 'Missing', 'restored' => 'Recovered/repaired and usable'] as $returnField => $returnLabel)
                        <label>{{ $returnLabel }}<input type="number" name="{{ $returnField }}" min="0" max="{{ $returnItem->pivot->quantity_approved }}" required value="{{ $returnRecord?->$returnField ?? 0 }}" @readonly($returnField === 'restored' && !$returnsAdmin) class="block w-full rounded border-slate-300"></label>
                    @endforeach
                    <label>Remarks<textarea name="remarks" required maxlength="2000" class="block w-full rounded border-slate-300">{{ $returnRecord?->remarks }}</textarea></label>
                    @if($returnRecord)<label class="sm:col-span-2">Reason for correction/recovery<textarea name="correction_reason" required maxlength="2000" class="block w-full rounded border-slate-300"></textarea></label>@endif
                    <button class="rounded bg-blue-700 p-3 font-semibold text-white sm:col-span-2">Save return record</button>
                </form>
            @elseif(!$returnsEnded)<p class="my-3 text-sm">Return recording becomes available after the scheduled end.</p>@endif
        </details>
    @endforeach
</section>
@endif
