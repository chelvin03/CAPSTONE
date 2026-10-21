@php($selectedType = old('event_type', $reservation->event_type ?? ''))
<div>
    <label for="event_type" class="mb-2 block text-sm font-semibold">Event Type *</label>
    <select id="event_type" name="event_type" required class="w-full rounded-lg border-slate-300 px-3 py-2" aria-describedby="event_type-error">
        <option value="">Select event type</option>
        @foreach(config('event-types') as $type)
            <option value="{{ $type }}" @selected($selectedType === $type)>{{ $type }}</option>
        @endforeach
    </select>
    @error('event_type')<p id="event_type-error" class="wizard-field-error text-red-600">{{ $message }}</p>@enderror
</div>
