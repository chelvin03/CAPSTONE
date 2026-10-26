@php
    $recordedOutcome = $reservation->eventOutcome()->with('recorder')->first();
    $eventEnded = !app(\App\Services\EventOutcomeService::class)->endAt($reservation)->isFuture();
    $canRecordOutcome = $eventEnded && in_array($reservation->status, ['approved', 'completed', 'no_show'], true);
    $canPreviewOutcome = !in_array($reservation->status, ['cancelled', 'rejected'], true);
    $outcomeAdmin = auth()->user()?->role === 'admin';
@endphp
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" id="event-outcome">
    <h2 class="text-lg font-bold">Event Attendance &amp; Outcome</h2>
    <p class="mt-2 text-sm text-slate-500">Record the actual attendance after the scheduled end time. This will be used for reports and system statistics.</p>
    @if(!$eventEnded)
        <p class="mt-2 text-sm" role="status"><i class="bi bi-info-circle-fill" aria-hidden="true"></i> Attendance can only be recorded after the scheduled event has ended.</p>
    @endif
    @if($recordedOutcome)
        <p><strong>{{ $recordedOutcome->outcome === 'no_show' ? 'No-show' : 'Completed' }}</strong>: {{ $recordedOutcome->actual_attendees }} actual / {{ $recordedOutcome->expected_attendees }} expected attendees.</p>
        <p class="my-2 text-sm">{{ $recordedOutcome->remarks }}</p>
        <p class="text-sm">Recorded by {{ $recordedOutcome->recorder?->full_name ?? 'Staff' }} on {{ $recordedOutcome->recorded_at->format('M j, Y g:i A') }}. Revision {{ $recordedOutcome->revision }}.</p>
    @endif
    @if($canPreviewOutcome && (!$recordedOutcome || $outcomeAdmin))
        <form method="POST" action="{{ route(($outcomeAdmin ? 'admin' : 'staff').'.reservations.outcome', $reservation) }}" class="mt-4 grid gap-3 sm:grid-cols-2" x-data="{ outcome: @js(old('outcome', $recordedOutcome?->outcome ?? 'completed')) }">
            @csrf
            <fieldset class="attendance-fields sm:col-span-2" @disabled(!$canRecordOutcome)>
            <input type="hidden" name="revision" value="{{ $recordedOutcome?->revision ?? 0 }}">
            <fieldset class="attendance-status"><legend class="mb-2 font-semibold">Attendance Status</legend><div><label><input type="radio" name="outcome" value="completed" x-model="outcome" required> <span>Attended / Event Completed<small>The event was held as scheduled.</small></span></label><label><input type="radio" name="outcome" value="no_show" x-model="outcome" required> <span>No-show / Did Not Attend<small>The requestor did not use the reserved slot.</small></span></label></div></fieldset>
            <label x-show="outcome === 'completed'">Actual Number of Attendees<input name="actual_attendees" type="number" min="1" max="{{ \App\Support\GymCapacity::MAX_ATTENDEES }}" step="1" :required="outcome === 'completed'" :disabled="outcome !== 'completed'" value="{{ old('actual_attendees', $recordedOutcome?->actual_attendees) }}" class="block w-full rounded border-slate-300"><small class="attendance-help">Enter the actual number of attendees who attended the event.</small></label>
            <p x-show="outcome === 'no_show'" class="text-sm">No-show records zero attendees and does not count as a cancellation.</p>
            <label class="attendance-remarks">Administrator Remarks (Optional)<textarea name="remarks" maxlength="500" rows="3" class="block w-full rounded border-slate-300">{{ old('remarks', $recordedOutcome?->remarks) }}</textarea></label>
            @if($recordedOutcome)<label class="sm:col-span-2">Reason for correction<textarea name="correction_reason" required maxlength="2000" class="block w-full rounded border-slate-300">{{ old('correction_reason') }}</textarea></label>@endif
            <p class="text-sm text-green-800 sm:col-span-2">Attendance records are saved and included in reports. You can only record attendance after the scheduled end time.</p>
            <button class="rounded bg-blue-700 px-4 py-3 font-semibold text-white sm:col-span-2"><i class="bi bi-file-earmark-check" aria-hidden="true"></i> {{ $recordedOutcome ? 'Save audited correction' : 'Save Attendance Record' }}</button>
            </fieldset>
        </form>
    @elseif(!$eventEnded)
        <p class="mt-3 text-sm">Only an approved event can receive an attendance record.</p>
    @elseif(!$recordedOutcome)
        <p class="mt-3 text-sm">Only an approved event can receive a completion or no-show record.</p>
    @endif
</section>
