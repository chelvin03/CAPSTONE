@extends('layouts.public')

@section('title', 'Reserve the Gymnasium')
@section('body-class', 'reservation-page')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/reservation-wizard.css') }}?v={{ filemtime(public_path('css/reservation-wizard.css')) }}">
@endpush

@section('content')
@php($gym = $facilities->firstWhere('facility_name', 'MCST Gymnasium') ?? $facilities->first())
<div class="reservation-wizard" x-data="reservationFlow()" x-cloak>
    <div class="wizard-intro">
        <h1>RESERVE THE GYMNASIUM</h1>
        <p>Provide your requestor information, reservation details, and review your confirmation. All requests are subject to review and approval.</p>
    </div>

    <!-- Steps nav -->
    <nav class="wizard-progress" aria-label="Reservation progress">
        <ol>
            @foreach ([1 => 'REQUESTOR INFORMATION', 2 => 'RESERVATION DETAILS', 3 => 'FACILITY & CONFIRMATION'] as $number => $label)
            <li :class="{ 'is-current': step === {{ $number }}, 'is-complete': step > {{ $number }} }" :aria-current="step === {{ $number }} ? 'step' : null">
                <span class="step-circle" aria-hidden="true"><span x-show="step <= {{ $number }}">{{ $number }}</span><i x-show="step > {{ $number }}" class="bi bi-check-lg"></i></span>
                <span class="step-label"><small>Step {{ $number }}</small>{{ $label }}<span class="sr-only" x-text="step > {{ $number }} ? ' (completed)' : (step === {{ $number }} ? ' (current)' : ' (upcoming)')"></span></span>
            </li>
            @endforeach
        </ol>
    </nav>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700">
            <p class="font-bold">Please correct the following errors:</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div x-show="message" x-text="message" role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-5 py-4 text-red-700"></div>

    <form @submit="if (step !== 3 || !bookingCanSubmit) $event.preventDefault()" x-ref="reservationForm" method="POST" action="{{ route('reservation.store') }}" enctype="multipart/form-data">
        @csrf

        <!-- Step 1 -->
        <section x-show="step === 1 && !verificationOpen" class="wizard-card overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="requestor-heading">
            <div class="wizard-card-heading border-b border-slate-200 p-6">
                <span class="wizard-heading-icon" aria-hidden="true"><i class="bi bi-person"></i></span>
                <div><p class="wizard-step-caption">STEP 1 ? REQUESTOR INFORMATION</p><h2 id="requestor-heading">Requestor Information</h2>
                <p class="wizard-heading-description">Provide your contact details to begin your reservation request.</p></div>
            </div>
            <div class="grid gap-5 p-6 sm:grid-cols-2">
                <div>
                    <label for="contact_person" class="mb-2 block font-semibold">Full Name <span class="required-marker" aria-hidden="true">*</span></label>
                    <input id="contact_person" name="contact_person" x-model="contactPerson" value="{{ old('contact_person') }}" placeholder="Enter your full name" autocomplete="name" aria-describedby="contact_person-error" :aria-invalid="Boolean(fieldErrors.contact_person)" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                    <p id="contact_person-error" class="wizard-field-error" x-show="fieldErrors.contact_person" x-text="fieldErrors.contact_person"></p>
                </div>
                <div>
                    <label for="contact_email" class="mb-2 block font-semibold">Email Address <span class="required-marker" aria-hidden="true">*</span></label>
                    <input id="contact_email" name="contact_email" type="email" x-model="email" value="{{ old('contact_email') }}" placeholder="you@example.com" autocomplete="email" aria-describedby="contact_email-error" :aria-invalid="Boolean(fieldErrors.contact_email)" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                    <p id="contact_email-error" class="wizard-field-error" x-show="fieldErrors.contact_email" x-text="fieldErrors.contact_email"></p>
                </div>
                <div>
                    <label for="contact_number" class="mb-2 block font-semibold">Contact Number <span class="required-marker" aria-hidden="true">*</span></label>
                    <input id="contact_number" name="contact_number" type="tel" x-model="contactNumber" minlength="11" maxlength="11" pattern="[0-9]{11}" @input="$event.target.value = $event.target.value.replace(/[^0-9]/g, '').slice(0, 11); contactNumber = $event.target.value" value="{{ old('contact_number') }}" placeholder="09XXXXXXXXX" autocomplete="tel" inputmode="numeric" aria-describedby="contact-number-help contact_number-error" :aria-invalid="Boolean(fieldErrors.contact_number)" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                    <p id="contact-number-help" class="wizard-field-help">Enter your 11-digit contact number.</p>
                    <p id="contact_number-error" class="wizard-field-error" x-show="fieldErrors.contact_number" x-text="fieldErrors.contact_number"></p>
                </div>
                <div>
                    <label for="reservation_type" class="mb-2 block font-semibold">Requestor Type <span class="required-marker" aria-hidden="true">*</span></label>
                    <select id="reservation_type" name="reservation_type" x-model="requestorType" aria-describedby="reservation_type-error" :aria-invalid="Boolean(fieldErrors.reservation_type)" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                        <option value="">Select Requestor Type</option>
                        @foreach (['internal' => 'Internal', 'external' => 'External'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p id="reservation_type-error" class="wizard-field-error" x-show="fieldErrors.reservation_type" x-text="fieldErrors.reservation_type"></p>
                </div>
                <div><label for="organization_department" class="mb-2 block font-semibold">Organization / Department</label><input name="organization_department" id="organization_department" x-model="organization" class="w-full rounded-lg border-slate-300 px-3 py-2"></div>
                        <div class="sm:col-span-2">
                            <label for="purpose" class="mb-2 block text-sm font-semibold">Purpose of Reservation *</label>
                            <textarea placeholder="Briefly describe your event and why you need the gymnasium" x-model="purpose" name="purpose" id="purpose" aria-describedby="purpose-error" aria-invalid="{{ $errors->has('purpose') ? 'true' : 'false' }}" rows="3" required class="w-full rounded-lg border-slate-300 px-3 py-2">{{ old('purpose') }}</textarea>
                            @error('purpose')<p id="purpose-error" class="wizard-field-error">{{ $message }}</p>@enderror
                        </div>

                <div class="wizard-info sm:col-span-2"><i class="bi bi-info-circle" aria-hidden="true"></i><p>Use an email address you can access. We will send a verification code and reservation updates to this address.</p></div>
                <div class="wizard-actions sm:col-span-2 text-right">
                    <span class="wizard-field-help">Fields marked <span class="required-marker">*</span> are required.</span>
                    <button type="button" @click="goToVerification" class="wizard-primary rounded-lg bg-blue-600 px-6 py-3 font-bold text-white">Next &rarr;</button>
                </div>
            </div>
        </section>

        <!-- Step 2: Verification -->
        <section x-show="step === 1 && verificationOpen" class="space-y-5" aria-labelledby="verification-heading">
            <div x-show="emailVerification.sent && emailVerification.showNotice" role="status" class="flex items-start justify-between gap-4 rounded-lg border border-cyan-200 bg-cyan-100 px-6 py-5 text-lg text-cyan-900">
                <p>A 6-digit verification code has been sent to your email:<br><span class="break-all" x-text="emailVerification.sentTo"></span></p>
                <button type="button" aria-label="Dismiss notification" @click="emailVerification.showNotice = false" class="text-3xl leading-none text-slate-500">&times;</button>
            </div>
            <div class="wizard-card overflow-hidden rounded-xl border border-slate-100 bg-white shadow-md">
                <div class="wizard-verification-heading border-b border-slate-200 px-6 py-10 text-center">
                    <p class="wizard-step-caption">STEP 1 ? EMAIL VERIFICATION</p>
                    <svg class="mx-auto mb-5 h-16 w-16 text-blue-600" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                        <rect x="5" y="10" width="52" height="38" rx="5" stroke="currentColor" stroke-width="3"/>
                        <path d="m7 17 24 16 24-16M7 43l16-12" stroke="currentColor" stroke-width="3"/>
                        <circle cx="47" cy="45" r="14" fill="currentColor" stroke="white" stroke-width="4"/>
                        <path d="m41 45 4 4 7-10" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <h2 id="verification-heading" class="text-3xl font-bold text-slate-900">Verify Your Email</h2>
                    <p class="mt-2 text-slate-500">Enter the 6-digit verification code sent to <strong class="break-all" x-text="email"></strong>.</p>
                </div>
                <div class="px-5 py-8 sm:px-8">
                    <label for="public-verification-code" class="mb-3 block text-center font-semibold text-slate-900">Enter Verification Code</label>
                    <input id="public-verification-code" x-ref="verificationCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" x-model="emailVerification.code" @input="emailVerification.code = $event.target.value.replace(/[^0-9]/g, '').slice(0, 6)" @keydown.enter.prevent="verifyCodeAndContinue" placeholder="000000" class="block w-full rounded-xl border-slate-300 py-4 text-center text-2xl font-bold focus:border-blue-500 focus:ring-4 focus:ring-blue-200">
                    <div class="mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-5">
                        <button type="button" @click="editDetails" :disabled="emailVerification.sending || emailVerification.verifying" class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-slate-500 disabled:opacity-50">&larr; Edit Details</button>
                        <button type="button" @click="verifyCodeAndContinue" :disabled="emailVerification.sending || emailVerification.verifying || !/^[0-9]{6}$/.test(emailVerification.code)" class="rounded-lg bg-blue-600 px-8 py-3 text-lg font-semibold text-white hover:bg-blue-700 disabled:opacity-50" x-text="emailVerification.verifying ? 'Verifying...' : 'Verify & Continue'"></button>
                    </div>
                    <div class="mt-5 text-center text-sm">
                        <button type="button" @click="sendCode" :disabled="emailVerification.sending || emailVerification.verifying || emailVerification.cooldown > 0" class="font-medium text-blue-600 disabled:text-slate-400" x-text="emailVerification.sending ? 'Sending code...' : (emailVerification.cooldown > 0 ? 'Resend code in ' + emailVerification.cooldown + 's' : 'Resend verification code')"></button>
                    </div>
                </div>
            </div>
        </section>

        @if ($emailVerified)
        <!-- Step 3: Event Info -->
        <input type="hidden" name="reservation_date" x-model="date" value="{{ old('reservation_date') }}">
        <section x-show="step === 2" class="wizard-card overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="event-heading">
            <div class="border-b border-slate-200 p-6">
                <p class="wizard-step-caption">STEP 2 ? RESERVATION DETAILS</p>
                <h2 id="event-heading" class="text-xl font-bold">Reservation Details</h2>
                <button type="button" @click="editDetails" class="mt-3 text-sm font-semibold text-blue-600">Edit Contact Details</button>
                <p class="mt-1 text-sm text-slate-500">Booking as: <strong x-text="contactPerson"></strong></p>
            </div>
            <div class="space-y-7 p-6">
                <div>
                    <h2 class="mb-4 text-xs font-bold uppercase text-blue-700">1. Event Schedule</h2>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="event_name" class="mb-2 block text-sm font-semibold">Event Name *</label>
                            <input placeholder="e.g. School sports program" name="event_name" id="event_name" aria-describedby="event_name-error" aria-invalid="{{ $errors->has('event_name') ? 'true' : 'false' }}" value="{{ old('event_name') }}" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                            @error('event_name')<p id="event_name-error" class="wizard-field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="event_type" class="mb-2 block text-sm font-semibold">Event Type</label>
                            <select name="event_type" id="event_type" required class="w-full rounded-lg border-slate-300 px-3 py-2"><option value="">Select event type</option>@foreach (['Basketball','Volleyball','Practice','Community Event','Meeting','Others'] as $type)<option @selected(old('event_type') === $type)>{{ $type }}</option>@endforeach</select>
                            @error('event_type')<p id="event_type-error" class="wizard-field-error">{{ $message }}</p>@enderror
                        </div>
<input type="hidden" name="facility_id" x-model="facility">
                        <div>
                            <label for="expected_attendees" class="mb-2 block text-sm font-semibold">Expected Number of Attendees *</label>
                            <input placeholder="Enter expected number of attendees" name="expected_attendees" id="expected_attendees" aria-describedby="expected_attendees-help expected_attendees-error" :aria-invalid="Boolean(fieldErrors.expected_attendees)" type="number" min="1" max="{{ \App\Support\GymCapacity::MAX_ATTENDEES }}" step="1" value="{{ old('expected_attendees') }}" @input="validateAttendance($event.target)" @invalid="step = 2; validateAttendance($event.target)" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                            <p id="expected_attendees-help" class="wizard-field-help">Maximum Gymnasium Capacity: 2,000 persons</p>
                            <p id="expected_attendees-error" class="wizard-field-error" role="alert" x-show="fieldErrors.expected_attendees" x-text="fieldErrors.expected_attendees">@error('expected_attendees'){{ $message }}@enderror</p>
                        </div>
                    </div>
                </div>


<div class="booking-calendar" aria-labelledby="booking-calendar-heading" :aria-busy="bookingLoading">
    <h3 id="booking-calendar-heading">Reservation Calendar / Facility Availability</h3>
    <p>Select an available date, then choose a time. Your selection updates the summary below.</p>
    <ul class="booking-legend" aria-label="Date availability legend"><li><span class="legend-dot legend-full" aria-hidden="true"></span>Fully Booked</li><li><span class="legend-dot legend-partial" aria-hidden="true"></span>Partially Booked &ndash; Available Time</li><li><span class="legend-dot legend-available" aria-hidden="true"></span>No Color &ndash; Fully Available</li></ul>
    <div class="booking-month-nav"><button type="button" @click="bookingMoveMonth(-1)" :disabled="!bookingCanMove(-1) || bookingLoading" aria-label="Previous calendar month">&larr;</button><h4 x-text="bookingMonthLabel"></h4><button type="button" @click="bookingMoveMonth(1)" :disabled="!bookingCanMove(1) || bookingLoading" aria-label="Next calendar month">&rarr;</button></div>
    <p x-show="bookingLoading" role="status">Loading date availability...</p>
    <div x-show="bookingError" role="alert"><p x-text="bookingError"></p><button type="button" @click="loadBookingMonth" class="wizard-secondary">Retry availability</button></div>
    <div class="booking-calendar-scroll" tabindex="0" role="region" aria-label="Monthly reservation calendar; scroll horizontally on small screens">
        <div class="booking-weekdays"><span>Sunday</span><span>Monday</span><span>Tuesday</span><span>Wednesday</span><span>Thursday</span><span>Friday</span><span>Saturday</span></div>
        <div class="booking-day-grid"><template x-for="(day, index) in bookingCells" :key="day ? day.date : 'blank-' + index"><div><template x-if="day"><button type="button" class="booking-day" :class="{'day-full': day.status === 'full', 'day-partial': day.status === 'partial', 'day-selected': date === day.date}" :disabled="bookingLoading || !!bookingError || !day.selectable || day.status === 'full'" @click="bookingSelectDate(day)" :aria-pressed="date === day.date" :aria-label="day.date + ': ' + (day.label || 'Loading') + (!day.selectable ? ', outside booking window' : '')"><span class="booking-day-number" x-text="day.number"></span><span class="booking-day-status" x-text="day.label || 'Loading...'"></span><span x-show="date === day.date">Selected date</span></button></template></div></template></div>
    </div>
    <p class="booking-notice" x-show="bookingOpening && !bookingError">Gymnasium Operating Hours: <span x-text="bookingTime(bookingOpening) + ' - ' + bookingTime(bookingClosing)"></span></p>
    <div class="booking-slot-section"><h4>Booking Time Slots <span x-show="date" x-text="' - ' + selectedDateLabel"></span></h4>
        <p x-show="!date">Select a date to see available and reserved times.</p>
        <p x-show="date && !bookingSelectedDay && !bookingLoading && !bookingError">View the selected date's month to see its time slots.</p>
        <p x-show="bookingSelectedDay?.status === 'full'">Fully Booked &mdash; no remaining gym time slots on this date.</p>
        <div class="booking-slot-grid"><template x-for="slot in (bookingSelectedDay?.slots || [])" :key="slot.start_time"><button type="button" class="booking-slot" :class="{'slot-reserved': !slot.available, 'slot-selected': startTime === slot.start_time && endTime === slot.end_time}" :disabled="!slot.available || bookingLoading || !!bookingError || !bookingSelectedDay?.selectable" @click="bookingSelectSlot(slot)" :aria-pressed="startTime === slot.start_time && endTime === slot.end_time"><span class="booking-slot-time" x-text="bookingTime(slot.start_time) + ' - ' + bookingTime(slot.end_time)"></span><span x-text="slot.label"></span><span x-show="slot.available && startTime === slot.start_time && endTime === slot.end_time">Selected time</span></button></template></div>
        <div class="booking-custom-times" x-show="bookingSelectedDay && !bookingLoading && !bookingError">
            <div class="booking-full-period">
                <div x-show="bookingAvailablePeriods.length > 1"><label for="booking-period">Available period</label><select id="booking-period" x-model="bookingPeriod"><option value="">Choose a period</option><template x-for="period in bookingAvailablePeriods" :key="period.start_time"><option :value="period.start_time" x-text="bookingTime(period.start_time) + ' - ' + bookingTime(period.end_time)"></option></template></select><p>Choose one uninterrupted available period. Reserved hours between periods cannot be included.</p></div>
                <button type="button" @click="bookingFullAvailable" :disabled="!bookingAvailablePeriods.length" class="wizard-secondary">Reserve Full Available Time</button>
            </div>
            <div><label for="start_time">Start Time</label><input type="time" id="start_time" name="start_time" x-model="startTime" min="08:00" max="21:00" step="60" required @change="updateBookingSummary" aria-describedby="booking-time-status"></div>
            <div><label for="end_time">End Time</label><input type="time" id="end_time" name="end_time" x-model="endTime" min="08:00" max="21:00" step="60" required @change="updateBookingSummary" aria-describedby="booking-time-status"></div>
            <p id="booking-time-status" class="booking-time-status" role="status" x-text="bookingTimeError ? 'Time Unavailable - ' + bookingTimeError : (bookingCanSubmit ? 'Time Available' : 'Choose your Start Time and End Time.')"></p>
        </div>
        <p class="booking-notice" x-show="startTime && endTime">Selected Reservation Time: <strong x-text="bookingTime(startTime) + ' - ' + bookingTime(endTime)"></strong></p>
        <p class="booking-notice" x-show="date && !bookingCanSubmit && !bookingLoading && !bookingError">Choose an available time slot before submitting.</p>
    </div>
</div>

                <!-- Attachment -->
                <div>
                    <h2 class="mb-4 text-xs font-bold uppercase text-blue-700">2. Attachment</h2>
                    <label for="permit" class="mb-2 block text-sm font-semibold">Upload Supporting Letter / Approval Document *</label>
                    <p class="mb-3">Upload valid approval letter or supporting document</p><input name="permit" id="permit" aria-describedby="permit-error" aria-invalid="{{ $errors->has('permit') ? 'true' : 'false' }}" type="file" accept=".pdf,.jpg,.jpeg,.png" required class="w-full rounded-lg border border-slate-300 p-2 text-sm">
                            @error('permit')<p id="permit-error" class="wizard-field-error">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500">Supported Formats: PDF, PNG, JPG (Max: 5MB)</p>
                </div>

<section x-show="requestorType === 'internal'" aria-labelledby="equipment-request-heading">
    <h2 id="equipment-request-heading" class="mb-3 text-lg font-bold text-blue-900">Equipment Request</h2>
    <p class="wizard-field-help">Equipment requests are subject to availability and approval by the Gym Administrator.</p>
    <div class="mt-4 grid gap-5 sm:grid-cols-2">
        @forelse ($equipment as $item)
        <div>
            <label for="equipment-{{ $item->id }}" class="mb-2 block font-semibold">{{ $item->equipment_name }} — Quantity Requested</label>
            <input id="equipment-{{ $item->id }}" name="equipment[{{ $item->id }}][quantity_requested]" type="number" min="1" max="100000" step="1" placeholder="Leave blank if not needed" value="{{ old('equipment.'.$item->id.'.quantity_requested') }}" :disabled="requestorType !== 'internal'" class="w-full rounded-lg border-slate-300 px-3 py-2">
            @error('equipment.'.$item->id.'.quantity_requested')<p class="wizard-field-error">{{ $message }}</p>@enderror
        </div>
        @empty
        <p class="wizard-field-help">No equipment is currently listed as available for requests.</p>
        @endforelse
    </div>
    @error('equipment')<p class="wizard-field-error" role="alert">{{ $message }}</p>@enderror
</section>
<div><label for="additional_notes" class="mb-2 block font-semibold">Additional Notes</label><textarea name="additional_notes" id="additional_notes" rows="3" class="w-full rounded-lg border-slate-300">{{ old('additional_notes') }}</textarea></div>
                <p x-show="hasSelectedConflict" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">The selected time overlaps a pending or reserved slot. Choose another time.</p>
<p class="wizard-info">Please make sure all reservation details are correct before proceeding.</p>
<div class="wizard-actions"><button type="button" @click="step = 1" class="wizard-secondary">&larr; Back</button><button type="button" @click="reviewReservation" :disabled="!bookingCanSubmit || !date || hasSelectedConflict || availabilityLoading || !!availabilityError || !facility" class="wizard-primary">Next &rarr;</button></div>
</div></section>
<section x-show="step === 3" class="wizard-card bg-white p-6" aria-labelledby="confirmation-heading"><p class="wizard-step-caption">STEP 3 OF 3</p><h2 id="confirmation-heading">Facility &amp; Confirmation</h2><p class="wizard-heading-description">Please review your reservation details carefully before submitting your request.</p>


<h3 class="confirmation-subheading">Facility Information</h3><dl class="facility-information"><div><dt>Facility</dt><dd>MCST Gymnasium</dd></div><div><dt>Location</dt><dd>MCST Gymnasium</dd></div><div><dt>Capacity</dt><dd>{{ number_format(\App\Support\GymCapacity::MAX_ATTENDEES) }} persons</dd></div><div><dt>Facility Status</dt><dd x-text="facilityStatus"></dd></div></dl>
<h3 class="confirmation-subheading">Reservation Summary</h3><dl class="reservation-summary"><template x-for="item in summary" :key="item.label"><div><dt x-text="item.label"></dt><dd x-text="item.value || '?'"></dd></div></template></dl>
<label class="flex items-start gap-3 rounded-xl bg-blue-50 p-4 text-blue-900"><input name="agreement" value="1" type="checkbox" required class="mt-1 rounded border-blue-300"><span>I confirm that the information is correct and understand that this request still requires administrator approval.</span></label>
<section class="gym-use-agreement" aria-labelledby="gym-use-agreement-heading">
    <h3 id="gym-use-agreement-heading">MCST Gymnasium Use Agreement</h3>
    <p>By submitting this reservation request, I understand and agree to follow the rules for the proper use of the MCST Gymnasium:</p>
    <ol class="gym-use-rules">
        <li><strong>No Food Inside the Gymnasium</strong><ul>
            <li>Food and meals are not allowed inside the gymnasium unless specifically permitted by the Gym Administrator.</li>
            <li>The requestor is responsible for informing all participants and guests about this rule.</li>
        </ul></li>
        <li><strong>Maintain Cleanliness</strong><ul>
            <li>The requestor and participants must keep the gymnasium clean during and after the event.</li>
            <li>Trash, decorations, bottles, papers, and other materials used during the event must be properly collected and disposed of.</li>
            <li>The area must be left clean and orderly after use.</li>
        </ul></li>
        <li><strong>Responsibility for Damages</strong><ul>
            <li>The requestor must take proper care of MCST property, including chairs, tables, equipment, facilities, and other items inside the gymnasium.</li>
            <li>If any chair, equipment, facility, or other MCST property is damaged due to the event or its participants, the incident must be reported to the Gym Administrator.</li>
            <li>The requestor may be held responsible for damages caused during their reserved event, subject to MCST policies and assessment by the authorized personnel.</li>
        </ul></li>
        <li><strong>Proper Use of the Gymnasium</strong><ul>
            <li>The gymnasium must only be used for the approved purpose, date, and time stated in the reservation.</li>
            <li>The requestor must follow instructions given by the Gym Administrator and authorized MCST personnel.</li>
        </ul></li>
    </ol>
    <label for="agreement_accepted" class="gym-use-consent">
        <input id="agreement_accepted" name="agreement_accepted" value="1" type="checkbox" required @checked(old('agreement_accepted'))
            aria-describedby="agreement_accepted-error" :aria-invalid="Boolean(fieldErrors.agreement_accepted)"
            @invalid="step = 3; fieldErrors.agreement_accepted = 'Please read and accept the MCST Gymnasium Use Agreement before submitting your reservation request.'; $event.target.setCustomValidity(fieldErrors.agreement_accepted)"
            @change="$event.target.setCustomValidity(''); delete fieldErrors.agreement_accepted">
        <span>I have read, understood, and agree to follow the MCST Gymnasium Use Agreement and accept responsibility for the proper use of the facility during my reservation. <span class="required-marker" aria-hidden="true">*</span></span>
    </label>
    <p id="agreement_accepted-error" class="wizard-field-error" role="alert" x-show="fieldErrors.agreement_accepted" x-text="fieldErrors.agreement_accepted">@error('agreement_accepted'){{ $message }}@enderror</p>
</section>
<div class="wizard-actions"><button type="button" @click="step = 2" class="wizard-secondary">&larr; Back</button><button type="submit" :disabled="!bookingCanSubmit || hasSelectedConflict || availabilityLoading || !!availabilityError || !emailVerification.verified || facilityStatus !== 'Available'" class="wizard-primary">Submit Reservation</button></div></section>
        @endif
    </form>
    <p class="wizard-privacy"><i class="bi bi-info-circle" aria-hidden="true"></i> Privacy reminder: provide only the personal information needed for this request. Personal data should be handled in accordance with the Data Privacy Act of 2012 (RA 10173).</p>

    <dialog x-ref="conflictDialog" aria-labelledby="conflict-title" aria-describedby="conflict-description"
        @cancel.prevent="closeConflictWarning" class="w-full max-w-md rounded-2xl border-0 bg-white p-6 shadow-xl"
        style="max-width: min(28rem, calc(100vw - 2rem));">
        <h2 id="conflict-title" class="text-xl font-bold text-red-700">This time is unavailable</h2>
        <p id="conflict-description" class="mt-3 text-slate-700">The selected time overlaps a pending reservation, confirmed booking, or blocked schedule. Please choose another time.</p>
        <button type="button" autofocus @click="closeConflictWarning" class="mt-6 w-full rounded-lg bg-blue-600 px-5 py-3 font-semibold text-white hover:bg-blue-700">Choose another time</button>
    </dialog>
    <style>dialog::backdrop { background: rgb(15 23 42 / 60%); }</style>
</div>

<script src="{{ asset('js-reservation-calendar.js') }}?v={{ filemtime(public_path('js-reservation-calendar.js')) }}"></script>
<script>
function reservationFlow() {
    return Object.defineProperties({
        bookingAvailabilityUrl: @js(route('reservation.availability')),
        maximumDate: @js(now()->addDays((int) \App\Models\SystemSetting::getValue('maximum_advance_days', 365))->toDateString()),
        step: {{ $emailVerified ? 2 : 1 }},
verificationOpen: @js(!$emailVerified && session()->has('public_email_challenge')),
organization: @js(old('organization_department', $requestorDetails['organization_department'] ?? '')),
purpose: @js(old('purpose', $requestorDetails['purpose'] ?? '')),
summary: [],
get facilityStatus() { return @js($gym?->status ?? 'unavailable') === 'maintenance' ? 'Maintenance' : (this.hasSelectedConflict ? (this.occupied.some(slot => slot.status === 'blackout' && this.startTime < slot.end_time.slice(0,5) && this.endTime > slot.start_time.slice(0,5)) ? 'Maintenance' : 'Reserved') : (@js($gym?->status ?? 'unavailable') === 'available' ? 'Available' : 'Reserved')); },
reviewReservation() {
this.message = '';
if (!this.validateAttendance(this.$refs.reservationForm.elements.namedItem('expected_attendees'))) { this.$refs.reservationForm.elements.namedItem('expected_attendees').reportValidity(); return; }
if (!this.bookingCanSubmit) { this.message = 'Select an available date and time slot before continuing.'; return; }
for (const field of this.$refs.reservationForm.querySelector('[aria-labelledby="event-heading"]').querySelectorAll('input,select,textarea')) { if (!field.reportValidity()) return; }
if (this.endTime <= this.startTime) { this.message = 'End time must be after start time.'; return; }
const fields = this.$refs.reservationForm.elements;
const value = name => fields.namedItem(name)?.value || '';
const time = t => new Date('2000-01-01T' + t).toLocaleTimeString([], {hour:'numeric', minute:'2-digit'});
if (!this.date) { this.message = "Select a reservation date."; return; }
this.summary = [ ['Requestor Name',this.contactPerson], ['Requestor Type',fields.namedItem('reservation_type').selectedOptions[0].text], ['Event Name',value('event_name')], ['Event Type',value('event_type')], ['Reservation Date',this.selectedDateLabel], ['Start Time',time(this.startTime)], ['End Time',time(this.endTime)], ['Number of Participants',value('expected_attendees')], ['Facility','MCST Gymnasium'], ['Uploaded Supporting Document',fields.namedItem('permit').files[0]?.name], ['Purpose',this.purpose], ['Organization / Department',this.organization], ['Additional Notes',value('additional_notes')] ].map(([label,value])=>({label,value}));
this.step = 3;
},
        init() {
            this.$watch('step', step => { if (step === 2 || step === 3) this.openBookingCalendar(); });
            this.$watch('conflictWarningKey', key => {
                if (key) this.$nextTick(() => {
                    if (this.conflictWarningKey && !this.$refs.conflictDialog.open) this.$refs.conflictDialog.showModal();
                });
                else if (this.$refs.conflictDialog.open) this.$refs.conflictDialog.close();
            });
            if (this.step === 2) this.openBookingCalendar();
            this.$watch('email', () => {
                this.emailVerification.verified = false;
                this.emailVerification.sent = false;
                this.emailVerification.code = '';
            });
        },
        validateAttendance(field) {
            field.setCustomValidity('');
            const value = Number(field.value);
            let error = '';
            if (value > @js(\App\Support\GymCapacity::MAX_ATTENDEES)) {
                error = 'The expected number of attendees exceeds the MCST Gymnasium maximum capacity of 2,000 persons. Please reduce the number of attendees to continue.';
            } else if (!field.value || !/^[0-9]+$/.test(field.value) || !Number.isInteger(value) || value < 1 || field.validity.badInput || field.validity.stepMismatch) {
                error = 'Enter a whole number of attendees between 1 and 2,000.';
            }
            field.setCustomValidity(error);
            if (error) this.fieldErrors.expected_attendees = error;
            else delete this.fieldErrors.expected_attendees;
            return !error;
        },
        get conflictWarningKey() {
            return this.step === 3 && !this.availabilityLoading && this.hasSelectedConflict
                ? [this.facility, this.date, this.startTime, this.endTime].join('|') : '';
        },
        closeConflictWarning() {
            this.$refs.conflictDialog.close();
            this.$nextTick(() => { if (this.step === 3) this.step = 2; this.$refs.reservationForm.querySelector('.booking-slot:not(:disabled)')?.focus(); });
        },
        availabilityLoading: false,
        availabilityError: '',
        message: '',
        fieldErrors: @js(collect($errors->messages())->map(fn ($messages) => $messages[0])->all()),
        occupied: [],
        contactPerson: @js(old('contact_person', $requestorDetails['contact_person'] ?? '')),
        email: @js(old('contact_email', $requestorDetails['contact_email'] ?? '')),
        contactNumber: @js(old('contact_number', $requestorDetails['contact_number'] ?? '')),
        requestorType: @js(old('reservation_type', $requestorDetails['reservation_type'] ?? '')),
        emailVerification: { sent: @js(session()->has('public_email_challenge')), sentTo: '', showNotice: false, verified: @js($emailVerified), sending: false, verifying: false, code: '', cooldown: 0, cooldownTimer: null },
        facility: @js((string) ($gym?->id ?? '')),
        date: @js(old('reservation_date', '')),
        startTime: @js(old('start_time', '')),
        endTime: @js(old('end_time', '')),
        minimumDate: @js(now()->addDays($minimumNoticeDays)->format('Y-m-d')),
        calendarMonth: @js(old('reservation_date') ? substr(old('reservation_date'), 0, 7) : now()->addDays($minimumNoticeDays)->format('Y-m')),

        get calendarMonthLabel() { const [year, month] = this.calendarMonth.split('-').map(Number); return new Date(year, month - 1, 1).toLocaleDateString([], {month: 'long', year: 'numeric'}); },
        get calendarDays() { const [year, month] = this.calendarMonth.split('-').map(Number); const firstDay = new Date(year, month - 1, 1).getDay(); const daysInMonth = new Date(year, month, 0).getDate(); const days = Array(firstDay).fill(null); for (let number = 1; number <= daysInMonth; number++) { const date = `${year}-${String(month).padStart(2, '0')}-${String(number).padStart(2, '0')}`; days.push({number, date, selectable: date >= this.minimumDate, label: new Date(year, month - 1, number).toLocaleDateString([], {weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'})}); } return days; },
        get selectedDateLabel() { if (!this.date) return ''; const [year, month, day] = this.date.split('-').map(Number); return new Date(year, month - 1, day).toLocaleDateString([], {month: 'long', day: 'numeric', year: 'numeric'}); },
        canMoveMonth(offset) { if (offset > 0) return true; const [year, month] = this.calendarMonth.split('-').map(Number); const target = new Date(year, month - 1 + offset, 1); const targetKey = `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}`; return targetKey >= this.minimumDate.slice(0, 7); },
        changeMonth(offset) { if (!this.canMoveMonth(offset)) return; const [year, month] = this.calendarMonth.split('-').map(Number); const target = new Date(year, month - 1 + offset, 1); this.calendarMonth = `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}`; },
        selectDate(value) { if (value < this.minimumDate) return; this.date = value; this.loadAvailability(); },
        get totalHours() { if (!this.startTime || !this.endTime) return ''; const [sh, sm] = this.startTime.split(':').map(Number); const [eh, em] = this.endTime.split(':').map(Number); return Math.max(0, ((eh * 60 + em) - (sh * 60 + sm)) / 60); },
        get hasSelectedConflict() { if (!this.startTime || !this.endTime || this.endTime <= this.startTime) return false; return this.occupied.some(slot => this.startTime < slot.end_time.slice(0, 5) && this.endTime > slot.start_time.slice(0, 5)); },

        async goToVerification() {
            this.message = '';
            this.fieldErrors = {};
            const fields = this.$refs.reservationForm.elements;
            this.contactPerson = fields.namedItem('contact_person').value.trim();
            this.email = fields.namedItem('contact_email').value.trim();
            this.contactNumber = fields.namedItem('contact_number').value.trim();
            this.requestorType = fields.namedItem('reservation_type').value;
            if (!this.contactPerson || !this.email || !this.contactNumber || !this.requestorType) {
                if (!this.contactPerson) this.fieldErrors.contact_person = 'Enter your full name.';
                if (!this.email) this.fieldErrors.contact_email = 'Enter your email address.';
                if (!this.contactNumber) this.fieldErrors.contact_number = 'Enter your contact number.';
                if (!this.requestorType) this.fieldErrors.reservation_type = 'Please select a requestor type.';
                this.message = 'Complete all required contact fields before continuing.';
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)) {
                this.fieldErrors.contact_email = 'Enter a valid email address.';
                this.message = 'Enter a valid email address.';
                return;
            }
            if (!/^[0-9]{11}$/.test(this.contactNumber)) {
                this.fieldErrors.contact_number = 'Contact number must contain exactly 11 digits.';
                this.message = 'Contact number must contain exactly 11 digits.';
                return;
            }
            await this.$nextTick();
            if (!this.purpose.trim()) { this.message = 'Enter the purpose of your reservation.'; return; }
            if (this.emailVerification.verified) { this.step = 2; return; }
            this.verificationOpen = true;
            if (!this.emailVerification.sent || this.emailVerification.sentTo !== this.email) this.sendCode();
        },

        continueToEvent() { this.message = ''; if (!this.emailVerification.verified) { this.message = 'Please verify your email before continuing.'; return; } this.step = 2; this.openBookingCalendar(); },

        startCooldown(seconds) {
            if (this.emailVerification.cooldownTimer) clearInterval(this.emailVerification.cooldownTimer);
            this.emailVerification.cooldown = seconds;
            if (!seconds) return;
            this.emailVerification.cooldownTimer = setInterval(() => {
                if (--this.emailVerification.cooldown <= 0) {
                    clearInterval(this.emailVerification.cooldownTimer);
                    this.emailVerification.cooldownTimer = null;
                }
            }, 1000);
        },

        async editDetails() {
            this.message = '';
            try {
                const response = await fetch(@js(route('reservation.edit_details')), {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': this.$refs.reservationForm.elements.namedItem('_token').value}
                });
                if (!response.ok) throw new Error('Unable to edit details. Please refresh and try again.');
                this.emailVerification.verified = false;
                this.emailVerification.sent = false;
                this.emailVerification.code = '';
                this.step = 1; this.verificationOpen = false;
            } catch (error) { this.message = error.message; }
        },

        async sendCode() {
            if (this.emailVerification.sending || this.emailVerification.cooldown > 0) return;
            this.message = '';
            this.emailVerification.verified = false;
            this.emailVerification.sending = true;
            try {
                const url = new URL(@js(route('reservation.send_code')), window.location.origin);
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || ''
                    },
                    body: JSON.stringify({ email: this.email, contact_person: this.contactPerson, contact_number: this.contactNumber, reservation_type: this.requestorType, organization_department: this.organization, purpose: this.purpose })
                });
                const data = await res.json();
                if (data.errors) {
                    this.fieldErrors = Object.fromEntries(Object.entries(data.errors).map(([key, errors]) => [key === 'email' ? 'contact_email' : key, errors[0]]));
                }
                this.startCooldown(data.retry_after || (res.status === 429 ? 60 : 0));
                if (!res.ok) throw new Error(data.message || 'Unable to send code');
                this.emailVerification.sent = true;
                this.emailVerification.sentTo = this.email;
                this.emailVerification.showNotice = true;
                this.$nextTick(() => this.$refs.verificationCode.focus());
                this.emailVerification.code = '';

            } catch (e) {
                this.message = e.message || 'Failed to send verification code.';
            } finally {
                this.emailVerification.sending = false;
            }
        },

        async verifyCode() {
            if (this.emailVerification.verifying || this.emailVerification.sending || !/^[0-9]{6}$/.test(this.emailVerification.code)) return false;
            this.emailVerification.verifying = true;
            try {
                const url = new URL(@js(route('reservation.verify_code')), window.location.origin);
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || ''
                    },
                    body: JSON.stringify({ email: this.email, code: this.emailVerification.code })
                });
                const data = await res.json();
                if (!res.ok) { this.message = data.message || 'Invalid verification code.'; return false; }
                if (data.verified) {
                    this.emailVerification.verified = true;
                    window.location.assign(data.redirect);
                    this.message = '';
                    return true;
                }
                this.message = 'Invalid verification code.';
                return false;
            } catch (e) {
                this.message = 'Invalid verification code.';
                return false;
            } finally {
                this.emailVerification.verifying = false;
            }
        },

        async verifyCodeAndContinue() {
            const ok = await this.verifyCode();
            if (ok) {
                this.message = 'Email verified. Opening Event Information...';
            }
        },

        async loadAvailability() { this.availabilityError = ''; if (!this.facility || !this.date) { this.occupied = []; return; } this.availabilityLoading = true; try { const url = new URL(@js(route('reservation.availability')), window.location.origin); url.searchParams.set('facility_id', this.facility); url.searchParams.set('date', this.date); const response = await fetch(url, {headers: {'Accept': 'application/json'}}); if (!response.ok) throw new Error('Availability could not be loaded. Please try again.'); this.occupied = await response.json(); } catch (error) { this.occupied = []; this.availabilityError = error.message; } finally { this.availabilityLoading = false; } },
        formatSlot(slot) { const format = time => new Date(`2000-01-01T${time}`).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'}); return `${format(slot.start_time)} - ${format(slot.end_time)}`; }
    }, Object.getOwnPropertyDescriptors(gymBookingCalendar()));
}
</script>
@endsection
