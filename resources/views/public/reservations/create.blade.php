@extends('layouts.public')

@section('title', 'Reserve the Gym')

@section('content')
<div class="mx-auto max-w-6xl" x-data="reservationFlow()" x-cloak>

    <!-- Steps nav -->
    <nav class="mb-6 flex items-center gap-4">
        <div class="flex-1 text-left"><span :class="step === 1 ? 'text-blue-600 font-bold' : 'text-emerald-600'"><span x-show="step > 1" aria-hidden="true">&#10003; </span>Contact Info</span></div>
        <div class="flex-1 text-center"><span :class="step === 2 ? 'text-blue-600 font-bold' : 'text-slate-400'">Step 2: Verification</span></div>
        <div class="flex-1 text-right"><span :class="step === 3 ? 'text-blue-600 font-bold' : 'text-slate-400'">Step 3: Event Info</span></div>
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

    <form x-ref="reservationForm" method="POST" action="{{ route('reservation.store') }}" enctype="multipart/form-data">
        @csrf

        <!-- Step 1 -->
        <section x-show="step === 1" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-6">
                <h1 class="text-2xl font-bold">Step 1: Requestor Details</h1>
                <p class="mt-2 text-slate-500">Provide your contact details to begin the reservation request.</p>
            </div>
            <div class="grid gap-5 p-6 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block font-semibold">Full Name *</label>
                    <input id="contact_person" name="contact_person" x-model="contactPerson" value="{{ old('contact_person') }}" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="mb-2 block font-semibold">Email Address *</label>
                    <input id="contact_email" name="contact_email" type="email" x-model="email" value="{{ old('contact_email') }}" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="mb-2 block font-semibold">Contact Number *</label>
                    <input id="contact_number" name="contact_number" type="tel" x-model="contactNumber" value="{{ old('contact_number') }}" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="mb-2 block font-semibold">Requestor Type *</label>
                    <select id="reservation_type" name="reservation_type" x-model="requestorType" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                        @foreach (['student' => 'Student', 'faculty' => 'Faculty', 'organization' => 'Organization', 'community' => 'Community'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 text-right">
                    <button type="button" @click="goToVerification" class="rounded-lg bg-blue-600 px-6 py-3 font-bold text-white">Continue to Verification &rarr;</button>
                </div>
            </div>
        </section>

        <!-- Step 2: Verification -->
        <section x-show="step === 2" class="space-y-5" aria-labelledby="verification-heading">
            <div x-show="emailVerification.sent && emailVerification.showNotice" role="status" class="flex items-start justify-between gap-4 rounded-lg border border-cyan-200 bg-cyan-100 px-6 py-5 text-lg text-cyan-900">
                <p>A 6-digit verification code has been sent to your email:<br><span class="break-all" x-text="emailVerification.sentTo"></span></p>
                <button type="button" aria-label="Dismiss notification" @click="emailVerification.showNotice = false" class="text-3xl leading-none text-slate-500">&times;</button>
            </div>
            <div class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-md">
                <div class="border-b border-slate-200 px-6 py-10 text-center">
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
        <section x-show="step === 3" class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-6">
                <h1 class="text-xl font-bold">Event Details & Requests</h1>
                <button type="button" @click="editDetails" class="mt-3 text-sm font-semibold text-blue-600">Edit Contact Details</button>
                <p class="mt-1 text-sm text-slate-500">Booking as: <strong x-text="contactPerson"></strong></p>
            </div>
            <div class="space-y-7 p-6">
                <div>
                    <h2 class="mb-4 text-xs font-bold uppercase text-blue-700">1. Event Schedule</h2>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold">Event Title *</label>
                            <input name="event_name" value="{{ old('event_name') }}" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold">Event Type</label>
                            <input name="event_type" value="{{ old('event_type') }}" class="w-full rounded-lg border-slate-300 px-3 py-2" placeholder="Sports Event">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold">Facility *</label>
                            <select name="facility_id" x-model="facility" @change="loadAvailability" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                                <option value="">Select facility</option>
                                @foreach($facilities as $facility)
                                    <option value="{{ $facility->id }}">{{ $facility->facility_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold">Estimated Participants *</label>
                            <input name="expected_attendees" type="number" min="1" value="{{ old('expected_attendees') }}" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-semibold">Purpose *</label>
                            <textarea name="purpose" rows="3" required class="w-full rounded-lg border-slate-300 px-3 py-2">{{ old('purpose') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200">
                    <div class="border-b border-slate-200 bg-slate-50 p-4 flex items-center justify-between">
                        <div class="text-sm font-semibold">Gymnasium Operational Slots (8:00 AM - 9:00 PM)</div>
                        <button type="button" class="rounded-md bg-slate-100 px-3 py-1 text-sm">Select a Date</button>
                    </div>
                    <div class="p-4">
                        <div class="mb-5 grid grid-cols-[2.5rem_1fr_2.5rem] items-center">
                            <button type="button" @click="changeMonth(-1)" :disabled="!canMoveMonth(-1)" class="rounded-lg p-2 text-xl font-bold text-blue-600 hover:bg-blue-50 disabled:cursor-not-allowed disabled:text-slate-300" aria-label="Previous month">&lsaquo;</button>
                            <h3 class="text-center text-lg font-bold text-slate-900" x-text="calendarMonthLabel"></h3>
                            <button type="button" @click="changeMonth(1)" :disabled="!canMoveMonth(1)" class="rounded-lg p-2 text-xl font-bold text-blue-600 hover:bg-blue-50 disabled:cursor-not-allowed disabled:text-slate-300" aria-label="Next month">&rsaquo;</button>
                        </div>
                        <div class="mb-2 grid grid-cols-7 gap-1 text-center text-[0.65rem] font-bold uppercase text-slate-500 sm:gap-2 sm:text-xs" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1 sm:gap-2" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
                            <template x-for="(day, index) in calendarDays" :key="index">
                                <div>
                                    <span x-show="!day" class="block h-10 sm:h-11"></span>
                                    <button x-show="day" type="button" @click="selectDate(day.date)" :disabled="!day.selectable" :class="date === day.date ? 'border-blue-600 bg-blue-600 font-bold text-white shadow-sm' : (day.selectable ? 'border-slate-200 bg-white text-slate-700 hover:border-blue-400 hover:bg-blue-50' : 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300')" class="h-10 w-full rounded-md border text-sm transition sm:h-11" :aria-label="day.label" :aria-pressed="date === day.date" x-text="day.number"></button>
                                </div>
                            </template>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <p class="text-slate-500">Please select a date from the calendar above</p>
                            <p x-show="date" class="font-semibold text-blue-700">Selected: <span x-text="selectedDateLabel"></span></p>
                        </div>

                        <div class="mt-4 text-center text-sm text-slate-500" x-show="!date">Click a calendar date to load available and occupied hours.</div>
                        <div class="mt-4" x-show="occupied.length == 0 && date">
                            <div class="text-emerald-700 text-sm">No existing reservations for this date.</div>
                        </div>
                        <div x-show="occupied.length" class="overflow-x-auto mt-4"><table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2 text-left">Occupied Time</th><th class="p-2 text-left">Status</th></tr></thead><tbody><template x-for="slot in occupied"><tr class="border-t"><td class="p-2" x-text="formatSlot(slot)"></td><td class="p-2 capitalize" x-text="slot.status.replace('_', ' ')"></td></tr></template></tbody></table></div>

                        <div class="mt-4 grid grid-cols-3 gap-3">
                            <div>
                                <label class="mb-2 block text-sm font-semibold">Start Time *</label>
                                <input name="start_time" type="time" x-model="startTime" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold">End Time *</label>
                                <input name="end_time" type="time" x-model="endTime" required class="w-full rounded-lg border-slate-300 px-3 py-2">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold">Total Hours</label>
                                <input readonly class="w-full rounded-lg border-slate-200 bg-slate-50 px-3 py-2" :value="totalHours ? totalHours + ' hours' : ''">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attachment -->
                <div>
                    <h2 class="mb-4 text-xs font-bold uppercase text-blue-700">2. Attachment</h2>
                    <label class="mb-2 block text-sm font-semibold">Upload Formal Request Letter / Permit *</label>
                    <input name="permit" type="file" accept=".pdf,.jpg,.jpeg,.png" required class="w-full rounded-lg border border-slate-300 p-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Supported Formats: PDF, PNG, JPG (Max: 5MB)</p>
                </div>

                <!-- Equipment -->
                <div>
                    <h2 class="mb-4 text-xs font-bold uppercase text-blue-700">3. Equipment Request (Optional)</h2>
                    <div class="rounded-lg border border-slate-200 p-4">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-100"><tr><th class="p-2 text-left">Select</th><th class="p-2 text-left">Item Description</th><th class="p-2 text-left">Quantity</th></tr></thead>
                            <tbody>
                                <tr class="border-t"><td class="p-2"><input type="checkbox" name="equipment[sound]" value="1"></td><td class="p-2">Sound System & Microphones</td><td class="p-2"><input name="equipment_qty[sound]" type="number" min="1" value="1" class="w-24 rounded border-slate-300 px-2 py-1"></td></tr>
                                <tr class="border-t"><td class="p-2"><input type="checkbox" name="equipment[chairs]" value="1"></td><td class="p-2">Monobloc Chairs</td><td class="p-2"><input name="equipment_qty[chairs]" type="number" min="1" value="50" class="w-24 rounded border-slate-300 px-2 py-1"></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p x-show="hasSelectedConflict" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">The selected time overlaps a pending or reserved slot. Choose another time.</p>
                <label class="flex items-start gap-3 rounded-xl bg-blue-50 p-4 text-sm text-blue-900"><input name="agreement" value="1" type="checkbox" required class="mt-1 rounded border-blue-300"><span>I confirm that the information is correct and understand that this request still requires administrator approval.</span></label>
                <div class="flex justify-end">
                    <button :disabled="!date || hasSelectedConflict || availabilityLoading || !emailVerification.verified" class="rounded-lg bg-emerald-600 px-6 py-3 font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">Submit Final Reservation</button>
                </div>
            </div>
        </section>

        @endif
    </form>

    <dialog x-ref="conflictDialog" aria-labelledby="conflict-title" aria-describedby="conflict-description"
        @cancel.prevent="closeConflictWarning" class="w-full max-w-md rounded-2xl border-0 bg-white p-6 shadow-xl"
        style="max-width: min(28rem, calc(100vw - 2rem));">
        <h2 id="conflict-title" class="text-xl font-bold text-red-700">This time is unavailable</h2>
        <p id="conflict-description" class="mt-3 text-slate-700">The selected time overlaps a pending reservation, confirmed booking, or blocked schedule. Please choose another time.</p>
        <button type="button" autofocus @click="closeConflictWarning" class="mt-6 w-full rounded-lg bg-blue-600 px-5 py-3 font-semibold text-white hover:bg-blue-700">Choose another time</button>
    </dialog>
    <style>dialog::backdrop { background: rgb(15 23 42 / 60%); }</style>
</div>

<script>
function reservationFlow() {
    return {
        step: {{ $initialStep }},
        init() {
            this.$watch('conflictWarningKey', key => {
                if (key) this.$nextTick(() => {
                    if (this.conflictWarningKey && !this.$refs.conflictDialog.open) this.$refs.conflictDialog.showModal();
                });
                else if (this.$refs.conflictDialog.open) this.$refs.conflictDialog.close();
            });
            if (this.step === 3) this.loadAvailability();
            this.$watch('email', () => {
                this.emailVerification.verified = false;
                this.emailVerification.sent = false;
                this.emailVerification.code = '';
            });
        },
        get conflictWarningKey() {
            return this.step === 3 && !this.availabilityLoading && this.hasSelectedConflict
                ? [this.facility, this.date, this.startTime, this.endTime].join('|') : '';
        },
        closeConflictWarning() {
            this.$refs.conflictDialog.close();
            this.$nextTick(() => this.$refs.reservationForm.elements.namedItem('start_time').focus());
        },
        availabilityLoading: false,
        availabilityError: '',
        message: '',
        occupied: [],
        contactPerson: @js(old('contact_person', $requestorDetails['contact_person'] ?? '')),
        email: @js(old('contact_email', $requestorDetails['contact_email'] ?? '')),
        contactNumber: @js(old('contact_number', $requestorDetails['contact_number'] ?? '')),
        requestorType: @js(old('reservation_type', $requestorDetails['reservation_type'] ?? 'student')),
        emailVerification: { sent: @js(session()->has('public_email_challenge')), sentTo: '', showNotice: false, verified: @js($emailVerified), sending: false, verifying: false, code: '', cooldown: 0, cooldownTimer: null },
        facility: @js(old('facility_id', '')),
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
            const fields = this.$refs.reservationForm.elements;
            this.contactPerson = fields.namedItem('contact_person').value.trim();
            this.email = fields.namedItem('contact_email').value.trim();
            this.contactNumber = fields.namedItem('contact_number').value.trim();
            this.requestorType = fields.namedItem('reservation_type').value;
            if (!this.contactPerson || !this.email || !this.contactNumber || !this.requestorType) {
                this.message = 'Complete all required contact fields before continuing.';
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)) {
                this.message = 'Enter a valid email address.';
                return;
            }
            if (!/^[0-9]{11}$/.test(this.contactNumber)) {
                this.message = 'Contact number must contain exactly 11 digits.';
                return;
            }
            await this.$nextTick();
            this.step = 2;
            if (!this.emailVerification.sent || this.emailVerification.sentTo !== this.email) this.sendCode();
        },

        continueToEvent() { this.message = ''; if (!this.emailVerification.verified) { this.message = 'Please verify your email before continuing.'; return; } this.step = 3; this.loadAvailability(); },

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
                this.step = 1;
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
                    body: JSON.stringify({ email: this.email, contact_person: this.contactPerson, contact_number: this.contactNumber, reservation_type: this.requestorType })
                });
                const data = await res.json();
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
    }
}
</script>
@endsection
