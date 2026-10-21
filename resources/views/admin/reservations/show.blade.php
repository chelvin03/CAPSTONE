@extends('layouts.app')

@section('title', 'Reservation Details')

@section('content')
<link rel="stylesheet" href="{{ asset('css/schedule-impact.css') }}?v={{ filemtime(public_path('css/schedule-impact.css')) }}"><script src="{{ asset('js-schedule-impact.js') }}?v={{ filemtime(public_path('js-schedule-impact.js')) }}"></script>
<div class="mx-auto max-w-6xl">

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <a
                href="{{ route('admin.reservations.index') }}"
                class="text-sm font-semibold text-blue-600 hover:underline"
            >
                ← Back to Reservations
            </a>

            <h1 class="mt-3 text-3xl font-bold text-slate-900">
                Reservation Details
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Reference Number:
                <span class="font-semibold text-slate-700">
                    {{ $reservation->reference_number }}
                </span>
            </p>
        </div>

        <span
            @class([
                'inline-flex w-fit rounded-full px-4 py-2 text-sm font-semibold',
                'bg-yellow-100 text-yellow-700' => in_array($reservation->status, ['new', 'pending', 'validated']),
                'bg-green-100 text-green-700' => $reservation->status === 'approved',
                'bg-red-100 text-red-700' => in_array($reservation->status, ['rejected', 'cancelled']),
                'bg-blue-100 text-blue-700' => $reservation->status === 'waiting_list',
                'bg-slate-100 text-slate-700' => ! in_array(
                    $reservation->status,
                    ['new', 'pending', 'validated', 'approved', 'rejected', 'cancelled', 'waiting_list'],
                    true
                ),
            ])
        >
            {{ ucwords(str_replace('_', ' ', $reservation->status)) }}
        </span>

    </div>

    @if(in_array($reservation->status, ['approved', 'completed'], true))
        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4"><p class="font-semibold text-green-800">Approved / For AR — staff preparation required</p><a class="mt-2 inline-block font-semibold text-blue-700 underline" href="{{ route('admin.reservations.receipt', $reservation) }}" target="_blank" rel="noopener"><i class="bi bi-printer" aria-hidden="true"></i> View AR Progress</a></div>
    @endif
    @if (session('success'))
        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
            <ul class="list-inside list-disc text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="reservation-detail-grid grid gap-6 lg:grid-cols-3">

        <div class="space-y-6 lg:col-span-2">
            @include('reservations.event-outcome')
            @include('reservations.equipment-returns')

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="gym-agreement-heading">
                <h2 id="gym-agreement-heading" class="mb-5 text-lg font-bold" style="color: #1E3A8A">Gymnasium Use Agreement</h2>
                <dl class="grid gap-5 text-sm sm:grid-cols-2">
                    <div><dt class="font-semibold text-slate-500">Agreement Status</dt><dd class="mt-2">
                        @if ($reservation->agreement_accepted)
                            <span class="inline-flex rounded-full px-3 py-1 font-semibold text-white" style="background-color: #10B981">Accepted</span>
                        @else
                            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-600">Not recorded</span>
                        @endif
                    </dd></div>
                    <div><dt class="font-semibold text-slate-500">Date Accepted</dt><dd class="mt-2 text-slate-700">{{ $reservation->agreement_accepted_at?->format('F d, Y g:i A') ?? 'Not recorded' }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Requestor Name</dt><dd class="mt-2 break-words text-slate-700">{{ $reservation->contact_person }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Reservation Reference Number</dt><dd class="mt-2 break-words text-slate-700">{{ $reservation->reference_number }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-lg font-bold text-slate-900">
                    Event Information
                </h2>

                <div class="grid gap-5 sm:grid-cols-2">

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Event Name
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ $reservation->event_name }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Reservation Type
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ ucwords(str_replace('_', ' ', $reservation->reservation_type)) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Expected Attendees
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ number_format($reservation->expected_attendees) }}
                        </p>
                        <p class="mt-2 text-sm text-slate-700">Gym Capacity: {{ number_format(\App\Support\GymCapacity::MAX_ATTENDEES) }}</p>
                        <p class="mt-1 text-sm text-slate-700">Capacity Usage: {{ round($reservation->expected_attendees / \App\Support\GymCapacity::MAX_ATTENDEES * 100, 1) }}%</p>
                    </div>

                    <div class="sm:col-span-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Purpose
                        </p>
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-700">
                            {{ $reservation->purpose }}
                        </p>
                    </div>

                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-lg font-bold text-slate-900">
                    Schedule
                </h2>

                <div class="grid gap-5 sm:grid-cols-2">

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Facility
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ $reservation->facility->facility_name ?? 'Not assigned' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Date
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ optional($reservation->reservation_date)->format('F d, Y') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Start Time
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ \Carbon\Carbon::parse($reservation->start_time)->format('g:i A') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            End Time
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ \Carbon\Carbon::parse($reservation->end_time)->format('g:i A') }}
                        </p>
                    </div>



                </div>
            </section>

            @include('admin.reservations.partials.attachments')
            @include('admin.reservations.partials.equipment-review')

        </div>

        <div class="space-y-6">

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-lg font-bold text-slate-900">
                    Requestor Information
                </h2>

                <div class="space-y-4">

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Name
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ $reservation->contact_person }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Contact Number
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ $reservation->contact_number }}
                        </p>
                    </div>

                </div>
            </section>

            @if(!in_array($reservation->status, ['cancelled', 'rejected', 'completed', 'no_show'], true))
            <section id="conflict-resolution" class="rounded-2xl border border-blue-200 bg-blue-50 p-6" style="scroll-margin-top:110px">
                <h2 class="mb-2 text-lg font-bold text-blue-900">Conflict Resolution / Reschedule</h2><p class="mb-4 text-sm text-blue-700">Move this reservation to an available schedule. If the target schedule is occupied, force assignment requires a choice for the affected bookings. Requestors receive email notices with the outcome and reason.</p>
                @php
                    $scheduleMinutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
                    $originalMinutes = $scheduleMinutes($reservation->end_time) - $scheduleMinutes($reservation->start_time);
                @endphp
                <form method="POST" action="{{route('admin.reservations.reschedule',$reservation)}}" class="grid gap-3 sm:grid-cols-2"
                    x-data="{
                        ...scheduleImpactReview(@js(route('admin.reservations.reschedule-preview', $reservation))),
                        duration: {{ $originalMinutes }}, closing: {{ $scheduleMinutes(config('gym.reservation.closing_time', '24:00')) }},
                        start: @js(old('start_time', substr($reservation->start_time, 0, 5))),
                        end: @js(old('end_time', substr($reservation->end_time, 0, 5))),
                        adjustDuration: @js((bool) old('adjust_duration', false)),
                        forceMode: @js((bool) old('force_assign', false)),
                        get sendProposal() { return !this.forceMode; },
                        selectedScheduleDate: @js(old('reservation_date', $reservation->reservation_date->format('Y-m-d'))),
                        originalScheduleDate: @js($reservation->reservation_date->format('Y-m-d')),
                        originalStart: @js(substr($reservation->start_time, 0, 5)),
                        originalEnd: @js(substr($reservation->end_time, 0, 5) === '00:00' ? '24:00' : substr($reservation->end_time, 0, 5)),
                        waitingOffer: @js($reservation->status === 'waiting_list'),
                        get durationError() {
                            if (!this.start || !this.end) return 'Choose a start and end time before proceeding.';
                            const minutes = time => time.split(':').reduce((hours, part) => hours * 60 + Number(part), 0);
                            const length = minutes(this.end) - minutes(this.start);
                            if (length &lt;= 0) return 'End time must be after start time.';
                            if (length &lt; 120) return 'Cannot proceed: reservations must be at least 2 hours. The selected duration is ' + length + ' minutes.';
                            if (minutes(this.end) &gt; this.closing) return 'Cannot proceed: the reservation must end before closing.';
                            if (!this.waitingOffer && this.selectedScheduleDate === this.originalScheduleDate && this.start === this.originalStart && this.end === this.originalEnd) return 'No schedule changes detected. Choose a different date or time.';
                            return '';
                        },
                        updateEnd() {
                            if (this.adjustDuration || !this.start) return;
                            const [h, m] = this.start.split(':').map(Number);
                            const finish = h * 60 + m + this.duration;
                            if (this.duration &lt; 120 || finish &gt; this.closing || finish &gt; 1440) {
                                this.end = ''; return;
                            }
                            this.end = String(Math.floor(finish / 60)).padStart(2, '0') + ':' + String(finish % 60).padStart(2, '0');
                        }
                    }" x-init="updateEnd()" @submit.prevent="reviewImpact($event)">@csrf @method('PATCH')
                    <p class="font-semibold">MCST Gymnasium</p>
                    <p class="sm:col-span-2 text-sm">Original duration: <strong>{{ intdiv(max(0, $originalMinutes), 60) }} hours {{ max(0, $originalMinutes) % 60 }} minutes</strong>. Preserved automatically unless you enable duration adjustment. The full booking and cleaning gap are checked before saving.</p>
                    <label>Reservation Date<input type="date" name="reservation_date" x-model="selectedScheduleDate" value="{{ old('reservation_date', $reservation->reservation_date->format('Y-m-d')) }}" required class="block w-full rounded-lg border-slate-300"></label>
                    <label>Start Time<input type="time" step="1800" name="start_time" value="{{ old('start_time', substr($reservation->start_time, 0, 5)) }}" x-model="start" @input="updateEnd()" required class="block w-full rounded-lg border-slate-300"></label>
                    <label>End Time<select name="end_time" x-model="end" :disabled="!adjustDuration" required class="block w-full rounded-lg border-slate-300">
                        <option value="">Choose end time</option>
                        @foreach(range(1, 48) as $halfHour)
                            @php($endOption = sprintf('%02d:%02d', intdiv($halfHour, 2), $halfHour % 2 * 30))
                            <option value="{{ $endOption }}" @selected(old('end_time', substr($reservation->end_time, 0, 5)) === $endOption)>{{ $endOption === '24:00' ? '12:00 midnight (end of selected date)' : \Carbon\Carbon::createFromTime(intdiv($halfHour, 2), $halfHour % 2 * 30)->format('g:i A') }}</option>
                        @endforeach
                    </select></label>
                    <label class="sm:col-span-2 flex items-center gap-2 text-sm"><input type="checkbox" name="adjust_duration" value="1" x-model="adjustDuration" @change="updateEnd()"> Deliberately change the original duration (explain why below)</label>
                    <p class="sm:col-span-2 text-sm text-red-700" role="alert" aria-live="polite" x-cloak x-show="durationError" x-text="durationError"></p>
                    <input type="hidden" name="send_proposal" :value="sendProposal ? 1 : 0" value="1">
                    @if($reservation->status === 'waiting_list')
                        <p class="sm:col-span-2 text-sm font-semibold">Waiting-list offer: sending a proposal keeps this request waiting. Acceptance will approve the offered slot after checking availability and equipment again. No other booking is displaced. The administrator chooses the recipient after reviewing priority and eligibility.</p>
                        <label class="sm:col-span-2 text-sm"><input type="checkbox" name="requirements_reviewed" value="1" :required="sendProposal"> I reviewed eligibility, required documents and the reason for offering this requestor a slot.</label>
                    @endif
                    <p class="sm:col-span-2 font-semibold" x-show="sendProposal">Send a new schedule proposal for requestor acceptance</p>
                    <p class="sm:col-span-2 text-sm text-blue-800" x-show="sendProposal">Original booking stays unchanged until accepted. The proposed slot is not held. The requestor has up to {{ $reservation->status === 'waiting_list' ? 24 : 48 }} hours, or until the proposed start time, to reply. Declining or expiry leaves the original booking unchanged. Proposals cannot displace other bookings. Ordinary rescheduling always requires the requestor to accept or decline.</p>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="force_assign" value="1" x-model="forceMode"> Force assign (direct admin action)</label><label>Conflict resolution<select name="conflict_resolution" :disabled="sendProposal" class="w-full rounded-lg border-slate-300"><option value="cancelled">Cancel affected bookings and email requestors to reschedule</option><option value="waiting_list">Move affected bookings to waiting list and email requestors</option></select></label>
                    <p class="sm:col-span-2 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700" x-show="forceMode">Force assign applies the schedule immediately after confirmation. The requestor does not accept or decline this action. Conflicting bookings will be cancelled or moved to the waiting list as selected below. Review all affected bookings before confirming.</p>
                    <p class="sm:col-span-2 text-sm text-blue-800">The selected resolution applies only to conflicting bookings when force assignment is enabled. To move another booking to a specific date/time, open that booking and reschedule it first. Cancelled bookings show the reason in public tracking. Schedule blocks must be released separately.</p>
                    <textarea name="reason" required rows="2" placeholder="Reason for schedule adjustment" class="sm:col-span-2 rounded-lg border-slate-300"></textarea><button type="submit" :disabled="impactBusy || !!durationError" class="sm:col-span-2 rounded-lg bg-blue-700 p-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" x-text="impactBusy ? 'Reviewing…' : 'Preview Changes & Email Notices'">Preview Changes & Email Notices</button>
                    @include('admin.schedule.partials.impact-review')
                </form>
                @php($scheduleProposals = \App\Models\ScheduleProposal::where('reservation_id', $reservation->id)->latest()->limit(5)->get())
                @if($scheduleProposals->isNotEmpty())
                    <div class="mt-5 border-t border-blue-200 pt-4">
                        <h3 class="font-bold">Replacement proposals</h3>
                        @foreach($scheduleProposals as $scheduleProposal)
                            <p class="my-3 text-sm">{{ $scheduleProposal->target['reservation_date'] }} {{ $scheduleProposal->target['start_time'] }}–{{ $scheduleProposal->target['end_time'] }}:
                                <strong>{{ $scheduleProposal->status === 'pending' ? ($scheduleProposal->expires_at->isPast() ? 'Expired' : ($scheduleProposal->original !== app(\App\Services\ScheduleProposalService::class)->snapshot($reservation) ? 'No longer applicable' : 'Awaiting requestor response')) : ucfirst($scheduleProposal->status) }}</strong>.
                                Email: {{ $scheduleProposal->mail_status }}. Reply deadline: {{ $scheduleProposal->expires_at->format('M j, Y g:i A') }}.
                                @if($scheduleProposal->mail_status === 'failed')<span class="text-red-700">Email failed. Check mail settings and send a new proposal.</span>@endif
                            </p>
                        @endforeach
                    </div>
                @endif
            </section>

            @endif
            @if (! in_array($reservation->status, ['approved', 'rejected', 'cancelled', 'completed', 'no_show'], true))

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

        <h2 class="mb-5 text-lg font-bold text-slate-900">
            Admin Actions
        </h2>

        <p class="mb-4 font-bold text-red-600">
            Current Status: {{ $reservation->status === 'pencil' ? 'Pencil booking — not reserved until approved' : $reservation->status }}
        </p>

        </h2>

{{-- APPROVE FORM --}}
<form
    method="POST"
    action="{{ route('admin.reservations.approve', $reservation) }}"
    class="mb-6"
    onsubmit="return confirm('Are you sure you want to approve this reservation?');"
>
    @csrf
    @method('PATCH')

    <input
        type="submit"
        value="{{ $reservation->status === 'pencil' ? 'Confirm Pencil Booking / For AR' : 'Approve / For AR' }}"
        style="
            display: block;
            width: 100%;
            min-height: 48px;
            padding: 12px 16px;
            background-color: #16a34a;
            color: #ffffff;
            border: 1px solid #15803d;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            opacity: 1;
            visibility: visible;
        "
    >
</form>

<div class="mb-5 border-t border-slate-200"></div>

<div class="mb-5 border-t border-slate-200"></div>

        {{-- REJECT FORM --}}
        <form
            method="POST"
            action="{{ route('admin.reservations.reject', $reservation) }}"
        >
            @csrf
            @method('PATCH')

            <label
                for="rejection_reason"
                class="mb-2 block text-sm font-semibold text-slate-700"
            >
                Rejection Reason
            </label>

            <textarea
                id="rejection_reason"
                name="rejection_reason"
                rows="3"
                required
                placeholder="Enter the reason for rejection"
                class="mb-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100"
            >{{ old('rejection_reason') }}</textarea>

            <button
                type="submit"
                class="block w-full rounded-lg bg-red-600 px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-red-700"
                onclick="return confirm('Are you sure you want to reject this reservation?');"
            >
                Reject Reservation
            </button>
        </form>

    </section>

@endif

            @if (in_array($reservation->status, ['approved', 'pencil'], true))
                <section class="rounded-2xl border border-red-200 bg-red-50 p-6">
                    <h2 class="mb-3 text-lg font-bold text-red-800">
                        Cancel Reservation
                    </h2>

                    <form
                        method="POST"
                        action="{{ route('admin.reservations.cancel', $reservation) }}"
                    >
                        @csrf
                        @method('PATCH')

                        <textarea
                            name="cancellation_reason"
                            rows="3"
                            required
                            placeholder="Enter cancellation reason"
                            class="mb-3 w-full rounded-lg border border-red-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-red-100"
                        ></textarea>

                        <button
                            type="submit"
                            class="w-full rounded-lg bg-red-700 px-4 py-3 text-sm font-semibold text-white hover:bg-red-800"
                        >
                            Cancel Reservation
                        </button>
                    </form>
                </section>
            @endif

        </div>

    </div>

</div>
@endsection
