@extends('layouts.app')

@section('title', 'Reservation Details')

@section('content')
<div class="mx-auto max-w-5xl">

    <div class="mb-6">
        <a
            href="{{ route('staff.reservations.index') }}"
            class="text-sm font-semibold text-blue-600 hover:underline"
        >
            ← Back to Reservations
        </a>

        <h1 class="mt-3 text-3xl font-bold text-slate-900">
            Reservation Details
        </h1>

        <p class="mt-1 text-sm text-slate-500">
            View-only page for staff.
        </p>
    </div>

    <div class="grid gap-6 md:grid-cols-2">

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

            <h2 class="mb-5 text-lg font-bold text-slate-900">
                Event Information
            </h2>

            <div class="space-y-4">

                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Reference Number
                    </p>

                    <p class="mt-1 text-sm font-semibold text-slate-800">
                        {{ $reservation->reference_number }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Event Name
                    </p>

                    <p class="mt-1 text-sm text-slate-700">
                        {{ $reservation->event_name }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Purpose
                    </p>

                    <p class="mt-1 text-sm text-slate-700">
                        {{ $reservation->purpose }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Status
                    </p>

                    <p class="mt-1 text-sm font-semibold text-slate-700">
                        {{ ucwords(str_replace('_', ' ', $reservation->status)) }}
                    </p>
                </div>

            </div>

        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

            <h2 class="mb-5 text-lg font-bold text-slate-900">
                Schedule and Requestor
            </h2>

            <div class="space-y-4">

                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Facility
                    </p>

                    <p class="mt-1 text-sm text-slate-700">
                        {{ $reservation->facility?->facility_name ?? 'Not assigned' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Reservation Date
                    </p>

                    <p class="mt-1 text-sm text-slate-700">
                        {{ optional($reservation->reservation_date)->format('F d, Y') }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Schedule
                    </p>

                    <p class="mt-1 text-sm text-slate-700">
                        {{ $reservation->start_time }} – {{ $reservation->end_time }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Contact Person
                    </p>

                    <p class="mt-1 text-sm text-slate-700">
                        {{ $reservation->contact_person }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Contact Number
                    </p>

                    <p class="mt-1 text-sm text-slate-700">
                        {{ $reservation->contact_number }}
                    </p>
                </div>

            </div>

        </section>

    </div>

    <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-4">
        <p class="text-sm font-semibold text-blue-800">
            Staff access is view-only.
        </p>

        <p class="mt-1 text-sm text-blue-700">
            Approval, rejection, cancellation, editing, and deletion are
            restricted to the administrator.
        </p>
    </div>

</div>
@endsection
