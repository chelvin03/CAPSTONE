@extends('layouts.public')

@section('title', 'Reserve the Gym')

@section('content')
<div class="mx-auto max-w-6xl" x-data="reservationFlow()" x-cloak>

    <!-- Steps nav -->
    <nav class="mb-6 flex items-center gap-4">
        <div class="flex-1 text-left"><span :class="step === 1 ? 'text-blue-600 font-bold' : 'text-slate-400'">Step 1: Contact Details</span></div>
        <div class="flex-1 text-right"><span :class="step === 2 ? 'text-blue-600 font-bold' : 'text-slate-400'">Step 2: Event Info</span></div>
    </nav>

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
                <p x-show="message" x-text="message" role="alert" class="sm:col-span-2 text-sm text-red-700"></p>
                <div class="sm:col-span-2 text-right">
                    <button type="button" @click="continueToEvent" class="rounded-lg bg-blue-600 px-6 py-3 font-bold text-white">Continue to Event Info →</button>
                </div>
            </div>
        </section>

        <!-- Step 2: Event Info -->
        <section x-show="step === 2" class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-6">
                <h1 class="text-xl font-bold">Event Details & Requests</h1>
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
                <div class="flex justify-between">
                    <button type="button" @click="step = 1" class="rounded-lg border border-slate-300 px-6 py-3 font-semibold">Back to Contact Details</button>
                    <input type="hidden" name="reservation_date" :value="date">
                    <button :disabled="hasSelectedConflict || availabilityLoading" class="rounded-lg bg-emerald-600 px-6 py-3 font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">✓ Submit Final Reservation</button>
                </div>
            </div>
        </section>

    </form>
</div>

<script>
function reservationFlow() {
    return {
        step: {{ $errors->any() ? 2 : 1 }},
        availabilityLoading: false,
        availabilityError: '',
        message: '',
        occupied: [],
        contactPerson: @js(old('contact_person', '')),
        email: @js(old('contact_email', '')),
        contactNumber: @js(old('contact_number', '')),
        requestorType: @js(old('reservation_type', 'student')),
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
        get hasSelectedConflict() { if (!this.startTime || !this.endTime) return false; return this.occupied.some(slot => this.startTime < slot.end_time.slice(0, 5) && this.endTime > slot.start_time.slice(0, 5)); },

        continueToEvent() { this.message = ''; if (!this.contactPerson.trim() || !this.email.trim() || !this.contactNumber.trim() || !this.requestorType) { this.message = 'Complete all required contact fields before continuing.'; return; } if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)) { this.message = 'Enter a valid email address.'; return; } if (!/^\d{11}$/.test(this.contactNumber)) { this.message = 'Contact number must contain exactly 11 digits.'; return; } this.step = 2; this.loadAvailability(); },

        async loadAvailability() { this.availabilityError = ''; if (!this.facility || !this.date) { this.occupied = []; return; } this.availabilityLoading = true; try { const url = new URL(@js(route('reservation.availability')), window.location.origin); url.searchParams.set('facility_id', this.facility); url.searchParams.set('date', this.date); const response = await fetch(url, {headers: {'Accept': 'application/json'}}); if (!response.ok) throw new Error('Availability could not be loaded. Please try again.'); this.occupied = await response.json(); } catch (error) { this.occupied = []; this.availabilityError = error.message; } finally { this.availabilityLoading = false; } },
        formatSlot(slot) { const format = time => new Date(`2000-01-01T${time}`).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'}); return `${format(slot.start_time)} – ${format(slot.end_time)}`; }
    }
}
</script>
@endsection
