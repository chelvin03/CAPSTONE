@extends('layouts.app')

@section('title', 'Edit Reservation')

@section('content')
<div class="mx-auto max-w-5xl">
    <div class="page-header">
        <div>
            <p class="text-sm font-medium text-blue-600">Administration</p>
            <h1 class="page-title">Edit Reservation</h1>
            <p class="page-description">Update {{ $reservation->reference_number }}.</p>
        </div>
        <a href="{{ route('admin.reservations.index') }}" class="btn-secondary">Back to Reservations</a>
    </div>

    @if ($errors->any())
        <div class="alert-error">
            <div>
                <p class="font-semibold">Please correct the following errors:</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.reservations.update', $reservation) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card p-6">
            <h2 class="mb-5 text-lg font-bold text-slate-900">Reservation Information</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="facility_id" class="field-label">Facility *</label>
                    <select id="facility_id" name="facility_id" required class="field">
                        <option value="">Select facility</option>
                        @foreach ($facilities as $facility)
                            <option value="{{ $facility->id }}" @selected((string) old('facility_id', $reservation->facility_id) === (string) $facility->id)>{{ $facility->facility_name }} - Capacity: {{ $facility->capacity }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="reservation_type" class="field-label">Reservation Type *</label>
                    <select id="reservation_type" name="reservation_type" required class="field">
                        @foreach (['school' => 'School Activity', 'community' => 'Community Activity', 'sports' => 'Sports Activity', 'government' => 'Government Activity', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('reservation_type', $reservation->reservation_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label for="event_name" class="field-label">Event Name *</label><input id="event_name" name="event_name" value="{{ old('event_name', $reservation->event_name) }}" maxlength="255" required class="field"></div>
                <div><label for="event_type" class="field-label">Event Type</label><input id="event_type" name="event_type" value="{{ old('event_type', $reservation->event_type) }}" maxlength="100" class="field"></div>
                <div class="md:col-span-2"><label for="purpose" class="field-label">Purpose *</label><textarea id="purpose" name="purpose" rows="3" required class="field">{{ old('purpose', $reservation->purpose) }}</textarea></div>
                <div><label for="contact_person" class="field-label">Contact Person *</label><input id="contact_person" name="contact_person" value="{{ old('contact_person', $reservation->contact_person) }}" required class="field"></div>
                <div><label for="contact_number" class="field-label">Contact Number *</label><input id="contact_number" name="contact_number" type="tel" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" value="{{ old('contact_number', $reservation->contact_number) }}" required class="field"></div>
                <div><label for="contact_email" class="field-label">Contact Email</label><input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $reservation->contact_email) }}" class="field"></div>
                <div><label for="expected_attendees" class="field-label">Estimated Participants *</label><input id="expected_attendees" name="expected_attendees" type="number" min="1" value="{{ old('expected_attendees', $reservation->expected_attendees) }}" required class="field"></div>
            </div>
        </section>

        <section class="card p-6">
            <h2 class="mb-5 text-lg font-bold text-slate-900">Schedule</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div><label for="reservation_date" class="field-label">Reservation Date *</label><input id="reservation_date" name="reservation_date" type="date" value="{{ old('reservation_date', $reservation->reservation_date->toDateString()) }}" required class="field"></div>
                <div><label for="start_time" class="field-label">Start Time *</label><input id="start_time" name="start_time" type="time" value="{{ old('start_time', substr((string) $reservation->start_time, 0, 5)) }}" required class="field"></div>
                <div><label for="end_time" class="field-label">End Time *</label><input id="end_time" name="end_time" type="time" value="{{ old('end_time', substr((string) $reservation->end_time, 0, 5)) }}" required class="field"></div>
                <div><label for="setup_time" class="field-label">Setup Time</label><input id="setup_time" name="setup_time" type="time" value="{{ old('setup_time', $reservation->setup_time ? substr((string) $reservation->setup_time, 0, 5) : '') }}" class="field"></div>
                <div><label for="cleanup_time" class="field-label">Cleanup Time</label><input id="cleanup_time" name="cleanup_time" type="time" value="{{ old('cleanup_time', $reservation->cleanup_time ? substr((string) $reservation->cleanup_time, 0, 5) : '') }}" class="field"></div>
            </div>
        </section>

        <section class="card p-6">
            <h2 class="mb-5 text-lg font-bold text-slate-900">Equipment Request</h2>
            <div class="grid gap-5 sm:grid-cols-[1fr_10rem]">
                <div><label for="requested_equipment" class="field-label">Equipment</label><input id="requested_equipment" name="requested_equipment" value="{{ old('requested_equipment', $reservation->requested_equipment) }}" maxlength="255" class="field"></div>
                <div><label for="requested_equipment_quantity" class="field-label">Quantity</label><input id="requested_equipment_quantity" name="requested_equipment_quantity" type="number" min="1" value="{{ old('requested_equipment_quantity', $reservation->requested_equipment_quantity) }}" class="field"></div>
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.reservations.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Update Reservation</button>
        </div>
    </form>
</div>
@endsection
