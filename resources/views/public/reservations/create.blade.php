@extends('layouts.public')

@section('title', 'Reserve the Gym')

@section('content')
<div
    class="mx-auto max-w-5xl"
    x-data="reservationFlow()"
    x-cloak
>
    <div class="mb-6 grid grid-cols-3 gap-3 text-center text-sm font-semibold sm:text-base">
        <div :class="step >= 1 ? 'text-blue-700' : 'text-slate-400'">
            <span x-show="step > 1" class="text-emerald-600">&#10003;</span>
            <span x-text="step > 1 ? 'Contact Details' : 'Step 1: Contact Details'"></span>
        </div>
        <div :class="step >= 2 ? 'text-blue-700' : 'text-slate-400'">
            <span x-show="step > 2" class="text-emerald-600">&#10003;</span>
            <span x-text="step > 2 ? 'Verified' : 'Step 2: Verification'"></span>
        </div>
        <div :class="step === 3 ? 'text-blue-700' : 'text-slate-400'">Step 3: Event Info</div>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700">
            <p class="font-bold">Please correct the following errors:</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('reservation.store') }}" enctype="multipart/form-data">
        @csrf

        <section x-show="step === 1" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-6 sm:p-8">
                <h1 class="text-2xl font-bold text-slate-900">Step 1: Requestor Details</h1>
                <p class="mt-2 text-slate-500">Provide your contact details to begin the reservation request.</p>
            </div>
            <div class="grid gap-5 p-6 sm:grid-cols-2 sm:p-8">
                <div>
                    <label for="contact_person" class="mb-2 block font-semibold">Full Name <span class="text-red-500">*</span></label>
                    <input id="contact_person" name="contact_person" x-model="contactPerson" value="{{ old('contact_person') }}" required class="w-full rounded-lg border-slate-300" placeholder="e.g. Juan Cruz">
                </div>
                <div>
                    <label for="contact_email" class="mb-2 block font-semibold">Email Address <span class="text-red-500">*</span></label>
                    <input id="contact_email" name="contact_email" type="email" x-model="email" value="{{ old('contact_email') }}" required class="w-full rounded-lg border-slate-300" placeholder="juan@example.com">
                </div>
                <div>
                    <label for="contact_number" class="mb-2 block font-semibold">Contact Number <span class="text-red-500">*</span></label>
                    <input id="contact_number" name="contact_number" x-model="contactNumber" value="{{ old('contact_number') }}" required class="w-full rounded-lg border-slate-300" placeholder="09123456789">
                </div>
                <div>
                    <label for="reservation_type" class="mb-2 block font-semibold">Requestor Type <span class="text-red-500">*</span></label>
                    <select id="reservation_type" name="reservation_type" x-model="requestorType" required class="w-full rounded-lg border-slate-300">
                        <option value="">Select affiliation</option>
                        @foreach (['student' => 'Student', 'faculty' => 'Faculty', 'organization' => 'Organization', 'community' => 'Community', 'external' => 'External Requestor'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <p x-show="message" x-text="message" class="text-sm text-red-600 sm:col-span-2" role="alert" aria-live="polite"></p>
                <div class="border-t border-slate-200 pt-5 text-right sm:col-span-2">
                    <button type="button" @click="sendCode" :disabled="loading" class="rounded-lg bg-blue-600 px-6 py-3 font-bold text-white disabled:opacity-50">
                        <span x-text="loading ? 'Sending...' : 'Continue to Verification →'"></span>
                    </button>
                </div>
            </div>
        </section>

        <section x-show="step === 2" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-8 text-center">
                <div class="text-5xl text-blue-600">&#9993;</div>
                <h1 class="mt-3 text-2xl font-bold">Verify Your Email</h1>
                <p class="mt-2 text-slate-500">Enter the 6-digit code sent to <strong x-text="email"></strong>.</p>
                <div x-show="notice" class="mx-auto mt-4 max-w-xl rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status">
                    <span x-text="notice"></span>
                </div>
            </div>
            <div class="p-8">
                <label for="verification_code" class="mb-2 block text-center font-semibold">Enter Verification Code</label>
                <input id="verification_code" x-model="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" class="mx-auto block w-full max-w-xl rounded-xl border-slate-300 text-center text-2xl font-bold tracking-[0.5em] focus:border-blue-500 focus:ring-blue-500" placeholder="000000" aria-describedby="verification-help">
                <p id="verification-help" x-show="message" x-text="message" class="mt-3 text-center text-sm text-red-600" role="alert" aria-live="polite"></p>
                <div class="mt-7 flex items-center justify-between border-t border-slate-200 pt-5">
                    <button type="button" @click="step = 1; message = ''" class="rounded-lg border border-slate-300 px-4 py-2 text-slate-600">← Edit Details</button>
                    <button type="button" @click="verifyCode" :disabled="loading" class="rounded-lg bg-blue-600 px-6 py-3 font-bold text-white disabled:opacity-50" x-text="loading ? 'Verifying...' : 'Verify & Continue'"></button>
                </div>
            </div>
        </section>

        <section x-show="step === 3" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-6">
                <h1 class="text-xl font-bold">Event Details & Requests</h1>
                <p class="mt-1 text-sm text-slate-500">Booking as: <strong x-text="contactPerson"></strong></p>
            </div>
            <div class="space-y-7 p-6">
                <div>
                    <h2 class="mb-4 text-xs font-bold uppercase text-blue-700">1. Event Schedule</h2>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div><label class="mb-2 block text-sm font-semibold">Event Title *</label><input name="event_name" value="{{ old('event_name') }}" required class="w-full rounded-lg border-slate-300"></div>
                        <div><label class="mb-2 block text-sm font-semibold">Event Type</label><input name="event_type" value="{{ old('event_type') }}" class="w-full rounded-lg border-slate-300" placeholder="Sports Event"></div>
                        <div><label class="mb-2 block text-sm font-semibold">Facility *</label><select name="facility_id" x-model="facility" @change="loadAvailability" required class="w-full rounded-lg border-slate-300"><option value="">Select facility</option>@foreach($facilities as $facility)<option value="{{ $facility->id }}">{{ $facility->facility_name }}</option>@endforeach</select></div>
                        <div><label class="mb-2 block text-sm font-semibold">Estimated Participants *</label><input name="expected_attendees" type="number" min="1" value="{{ old('expected_attendees') }}" required class="w-full rounded-lg border-slate-300"></div>
                        <div class="md:col-span-2"><label class="mb-2 block text-sm font-semibold">Purpose *</label><textarea name="purpose" rows="3" required class="w-full rounded-lg border-slate-300">{{ old('purpose') }}</textarea></div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200">
                    <div class="grid gap-4 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-3">
                        <div><label class="mb-2 block text-sm font-semibold">Reservation Date *</label><input name="reservation_date" type="date" min="{{ now()->format('Y-m-d') }}" x-model="date" @change="loadAvailability" required class="w-full rounded-lg border-slate-300"></div>
                        <div><label class="mb-2 block text-sm font-semibold">Start Time *</label><input name="start_time" type="time" x-model="startTime" required class="w-full rounded-lg border-slate-300"></div>
                        <div><label class="mb-2 block text-sm font-semibold">End Time *</label><input name="end_time" type="time" x-model="endTime" required class="w-full rounded-lg border-slate-300"><p x-show="totalHours" class="mt-1 text-xs text-slate-500">Total: <span x-text="totalHours"></span> hours</p></div>
                    </div>
                    <div class="p-4">
                        <div class="mb-3 flex items-center gap-4 text-xs"><span><i class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></i>Available</span><span><i class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-rose-500"></i>Occupied</span></div>
                        <p x-show="availabilityLoading" class="py-6 text-center text-sm text-blue-700" role="status">Checking availability…</p>
                        <p x-show="availabilityError" x-text="availabilityError" class="py-6 text-center text-sm text-red-700" role="alert"></p>
                        <p x-show="!availabilityLoading && !date" class="py-6 text-center text-sm text-slate-500">Select a date to view occupied hours.</p>
                        <p x-show="!availabilityLoading && !availabilityError && date && occupied.length === 0" class="py-6 text-center text-sm text-emerald-700">No existing reservations for this date.</p>
                        <div x-show="occupied.length" class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2 text-left">Occupied Time</th><th class="p-2 text-left">Status</th></tr></thead><tbody><template x-for="slot in occupied"><tr class="border-t"><td class="p-2" x-text="formatSlot(slot)"></td><td class="p-2 capitalize" x-text="slot.status.replace('_', ' ')"></td></tr></template></tbody></table></div>
                    </div>
                </div>

                <div><h2 class="mb-4 text-xs font-bold uppercase text-blue-700">2. Attachment</h2><label class="mb-2 block text-sm font-semibold">Upload Formal Request Letter / Permit *</label><input name="permit" type="file" accept=".pdf,.jpg,.jpeg,.png" required class="w-full rounded-lg border border-slate-300 p-2 text-sm"><p class="mt-1 text-xs text-slate-500">PDF, PNG, JPG, or JPEG (maximum 5 MB)</p></div>

                @if($equipment->isNotEmpty())
                    <div><h2 class="mb-4 text-xs font-bold uppercase text-blue-700">3. Equipment Request (Optional)</h2><div class="divide-y rounded-lg border">@foreach($equipment as $item)<div class="grid items-center gap-3 p-3 sm:grid-cols-[1fr_8rem]"><div><p class="font-semibold">{{ $item->equipment_name }}</p><p class="text-xs text-slate-500">Available: {{ $item->total_quantity }} {{ $item->unit }}</p></div><input name="equipment[{{ $item->id }}][quantity_requested]" type="number" min="1" max="{{ $item->total_quantity }}" class="rounded-lg border-slate-300" placeholder="Quantity"></div>@endforeach</div></div>
                @endif

                <label class="flex items-start gap-3 rounded-xl bg-blue-50 p-4 text-sm text-blue-900"><input name="agreement" value="1" type="checkbox" required class="mt-1 rounded border-blue-300"><span>I confirm that the information is correct and understand that this request still requires administrator approval.</span></label>
                <div class="flex justify-end"><button class="rounded-lg bg-emerald-600 px-6 py-3 font-bold text-white hover:bg-emerald-700">✓ Submit Final Reservation</button></div>
            </div>
        </section>
    </form>
</div>

<script>
function reservationFlow() {
    return {
        step: {{ $errors->any() ? 3 : 1 }}, loading: false, availabilityLoading: false, availabilityError: '', message: '', notice: '', code: '', occupied: [],
        contactPerson: @js(old('contact_person', '')), email: @js(old('contact_email', '')), contactNumber: @js(old('contact_number', '')), requestorType: @js(old('reservation_type', '')),
        facility: @js(old('facility_id', '')), date: @js(old('reservation_date', '')), startTime: @js(old('start_time', '')), endTime: @js(old('end_time', '')),
        get totalHours() { if (!this.startTime || !this.endTime) return ''; const [sh, sm] = this.startTime.split(':').map(Number); const [eh, em] = this.endTime.split(':').map(Number); return Math.max(0, ((eh * 60 + em) - (sh * 60 + sm)) / 60); },
        async request(url, data) { const response = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value}, body: JSON.stringify(data)}); const body = await response.json(); if (!response.ok) throw new Error(body.message || Object.values(body.errors || {})[0]?.[0] || 'Unable to continue.'); return body; },
        async sendCode() { this.message = ''; this.notice = ''; if (!this.contactPerson.trim() || !this.email.trim() || !this.contactNumber.trim() || !this.requestorType) { this.message = 'Complete all required contact fields before continuing.'; return; } this.loading = true; try { const result = await this.request(@js(route('reservation.verification.send')), {contact_person: this.contactPerson, contact_email: this.email, contact_number: this.contactNumber, reservation_type: this.requestorType}); this.step = 2; if (result.development_code) { this.notice = `Local testing code: ${result.development_code}. Configure SMTP before deploying the system.`; } } catch (error) { this.message = error.message; } finally { this.loading = false; } },
        async verifyCode() { this.message = ''; if (!/^\d{6}$/.test(this.code)) { this.message = 'Enter the complete 6-digit verification code.'; return; } this.loading = true; try { await this.request(@js(route('reservation.verification.verify')), {contact_email: this.email, verification_code: this.code}); this.step = 3; this.loadAvailability(); } catch (error) { this.message = error.message; } finally { this.loading = false; } },
        async loadAvailability() { this.availabilityError = ''; if (!this.facility || !this.date) { this.occupied = []; return; } this.availabilityLoading = true; try { const url = new URL(@js(route('reservation.availability')), window.location.origin); url.searchParams.set('facility_id', this.facility); url.searchParams.set('date', this.date); const response = await fetch(url, {headers: {'Accept': 'application/json'}}); if (!response.ok) throw new Error('Availability could not be loaded. Please try again.'); this.occupied = await response.json(); } catch (error) { this.occupied = []; this.availabilityError = error.message; } finally { this.availabilityLoading = false; } },
        formatSlot(slot) { const format = time => new Date(`2000-01-01T${time}`).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'}); return `${format(slot.start_time)} – ${format(slot.end_time)}`; }
    }
}
</script>
@endsection
