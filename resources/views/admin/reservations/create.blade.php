@extends('layouts.app')

@section('title', 'New Reservation')

@section('content')
<div class="mx-auto max-w-6xl" x-data="reservationFlow()" x-cloak>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-blue-600">Administration</p>

            <h1 class="text-3xl font-bold text-slate-900">
                New Reservation
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Enter the reservation details and requested equipment.
            </p>
        </div>

        <a
            href="{{ route('admin.reservations.index') }}"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
        >
            Back to Reservations
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
            <p class="font-semibold text-red-700">
                Please correct the following errors:
            </p>

            <ul class="mt-2 list-inside list-disc text-sm text-red-600">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.reservations.store') }}"
        class="space-y-6"
    >
        @csrf

        <div class="mb-6">
            <h2 class="text-xl font-bold">Event Details & Requests</h2>
            <p class="mt-1 text-sm text-slate-500">Booking as: <strong>{{ auth()->user()->first_name ?? auth()->user()->name ?? '' }} {{ auth()->user()->last_name ?? '' }}</strong></p>
        </div>

        @include('admin.reservations._form')

        <div class="flex justify-end gap-3">
            <a
                href="{{ route('admin.reservations.index') }}"
                class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
                Save Reservation
            </button>
        </div>
    </form>

</div>

<script>
// Copied reservationFlow from public reservation form to enable calendar and availability interactions
function reservationFlow() {
    return {
        step: {{ $errors->any() ? 2 : 1 }}, availabilityLoading: false, availabilityError: '', message: '', occupied: [],
        contactPerson: @js(old('contact_person', '')), email: @js(old('contact_email', '')), contactNumber: @js(old('contact_number', '')), requestorType: @js(old('reservation_type', 'student')),
        facility: @js(old('facility_id', '')), date: @js(old('reservation_date', '')), startTime: @js(old('start_time', '')), endTime: @js(old('end_time', '')),
        minimumDate: @js(now()->addDays($minimumNoticeDays)->format('Y-m-d')), calendarMonth: @js(old('reservation_date') ? substr(old('reservation_date'), 0, 7) : now()->addDays($minimumNoticeDays)->format('Y-m')),
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
