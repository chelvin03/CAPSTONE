@extends('layouts.app')

@section('title', 'Reservation Details')

@section('content')
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

    <div class="grid gap-6 lg:grid-cols-3">

        <div class="space-y-6 lg:col-span-2">

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
                            Event Type
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ $reservation->event_type ?? 'Not specified' }}
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

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Setup Time
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ $reservation->setup_time
                                ? \Carbon\Carbon::parse($reservation->setup_time)->format('g:i A')
                                : 'Not specified' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Cleanup Time
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ $reservation->cleanup_time
                                ? \Carbon\Carbon::parse($reservation->cleanup_time)->format('g:i A')
                                : 'Not specified' }}
                        </p>
                    </div>

                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-lg font-bold text-slate-900">
                    Requested Equipment
                </h2>

                @forelse ($reservation->equipment as $item)
                    <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-0">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">
                                {{ $item->equipment_name }}
                            </p>
                            <p class="text-xs text-slate-500">
                                Requested: {{ $item->pivot->quantity_requested }}
                            </p>
                        </div>

                        <p class="text-sm font-semibold text-slate-700">
                            Approved:
                            {{ $item->pivot->quantity_approved ?? 0 }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">
                        No equipment requested.
                    </p>
                @endforelse
            </section>

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

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Account
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            {{ $reservation->user->email ?? 'Not available' }}
                        </p>
                    </div>

                </div>
            </section>

            @if (! in_array($reservation->status, ['approved', 'rejected', 'cancelled'], true))

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

        <h2 class="mb-5 text-lg font-bold text-slate-900">
            Admin Actions
        </h2>

        <p class="mb-4 font-bold text-red-600">
            Current Status: {{ $reservation->status }}
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
        value="Approve Reservation"
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

            @if ($reservation->status === 'approved')
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
