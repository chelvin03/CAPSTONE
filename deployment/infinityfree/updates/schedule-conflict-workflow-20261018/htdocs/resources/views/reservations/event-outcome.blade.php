@php
    $recordedOutcome = $reservation->eventOutcome()->with('recorder')->first();
    $eventEnded = !app(\App\Services\EventOutcomeService::class)->endAt($reservation)->isFuture();
    $canRecordOutcome = $eventEnded && in_array($reservation->status, ['approved', 'completed', 'no_show'], true);
    $outcomeAdmin = auth()->user()?->role === 'admin';
@endphp
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" id="event-outcome">
    <h2 class="text-lg font-bold">Event outcome and actual attendance</h2>
    <p class="my-3 text-sm">Record what actually happened after the scheduled end. Completing the acknowledgement receipt does not complete the event. Cancelled bookings are excluded from no-shows.</p>
    @if($recordedOutcome)
        <p><strong>{{ $recordedOutcome->outcome === 'no_show' ? 'No-show' : 'Completed' }}</strong>: {{ $recordedOutcome->actual_attendees }} actual / {{ $recordedOutcome->expected_attendees }} expected attendees.</p>
        <p class="my-2 text-sm">{{ $recordedOutcome->remarks }}</p>
        <p class="text-sm">Recorded by {{ $recordedOutcome->recorder?->full_name ?? 'Staff' }} on {{ $recordedOutcome->recorded_at->format('M j, Y g:i A') }}. Revision {{ $recordedOutcome->revision }}.</p>
    @endif
    @if($canRecordOutcome && (!$recordedOutcome || $outcomeAdmin))
        <form method="POST" action="{{ route(($outcomeAdmin ? 'admin' : 'staff').'.reservations.outcome', $reservation) }}" class="mt-4 grid gap-3 sm:grid-cols-2" x-data="{ outcome: @js(old('outcome', $recordedOutcome?->outcome ?? 'completed')) }">
            @csrf
            <input type="hidden" name="revision" value="{{ $recordedOutcome?->revision ?? 0 }}">
            <label>Outcome<select name="outcome" x-model="outcome" class="block w-full rounded border-slate-300"><option value="completed">Completed — event took place</option><option value="no_show">No-show — requestor did not attend</option></select></label>
            <label x-show="outcome === 'completed'">Actual attendees<input name="actual_attendees" type="number" min="1" max="{{ \App\Support\GymCapacity::MAX_ATTENDEES }}" step="1" :required="outcome === 'completed'" :disabled="outcome !== 'completed'" value="{{ old('actual_attendees', $recordedOutcome?->actual_attendees) }}" class="block w-full rounded border-slate-300"></label>
            <p x-show="outcome === 'no_show'" class="text-sm">No-show records zero attendees and does not count as a cancellation.</p>
            <label class="sm:col-span-2">Event remarks<textarea name="remarks" required maxlength="2000" class="block w-full rounded border-slate-300">{{ old('remarks', $recordedOutcome?->remarks) }}</textarea></label>
            @if($recordedOutcome)<label class="sm:col-span-2">Reason for correction<textarea name="correction_reason" required maxlength="2000" class="block w-full rounded border-slate-300">{{ old('correction_reason') }}</textarea></label>@endif
            <button class="rounded bg-blue-700 px-4 py-3 font-semibold text-white sm:col-span-2">{{ $recordedOutcome ? 'Save audited correction' : 'Record event outcome' }}</button>
        </form>
    @elseif(!$eventEnded)
        <p class="mt-3 text-sm">Available after {{ app(\App\Services\EventOutcomeService::class)->endAt($reservation)->format('M j, Y g:i A') }} ({{ config('app.timezone') }}). Events are never automatically completed.</p>
    @elseif(!$recordedOutcome)
        <p class="mt-3 text-sm">Only an approved event can receive a completion or no-show record.</p>
    @endif
</section>
