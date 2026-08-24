@extends('layouts.public')

@section('title', 'Reservation Submitted')

@section('content')

    <div class="mx-auto max-w-xl">

        <main class="rounded-2xl bg-white p-8 text-center shadow-lg">

            <div
                class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl font-bold text-green-700"
            >
                ✓
            </div>

            <h1 class="text-2xl font-bold text-slate-900">
                Reservation Submitted
            </h1>

            <p class="mt-3 text-slate-600">
                Your reservation request was submitted successfully and is
                waiting for review by the gym administrator.
            </p>

            <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-5">

                <p class="text-sm font-semibold text-blue-700">
                    Your Reference Number
                </p>

                <p class="mt-2 break-all text-2xl font-bold text-blue-900">
                    {{ $reservation->reference_number }}
                </p>

            </div>

            <div class="mt-6 rounded-xl bg-slate-50 p-5 text-left">

                <div class="space-y-3">

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Requestor
                        </p>

                        <p class="font-semibold text-slate-800">
                            {{ $reservation->contact_person }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Event
                        </p>

                        <p class="font-semibold text-slate-800">
                            {{ $reservation->event_name }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Facility
                        </p>

                        <p class="font-semibold text-slate-800">
                            {{ $reservation->facility?->facility_name ?? 'Not available' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Reservation Date
                        </p>

                        <p class="font-semibold text-slate-800">
                            {{ $reservation->reservation_date->format('F d, Y') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Schedule
                        </p>

                        <p class="font-semibold text-slate-800">
                            {{ \Carbon\Carbon::parse($reservation->start_time)->format('g:i A') }}
                            –
                            {{ \Carbon\Carbon::parse($reservation->end_time)->format('g:i A') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Status
                        </p>

                        <span
                            class="mt-1 inline-flex rounded-full bg-yellow-100 px-3 py-1 text-sm font-semibold text-yellow-800"
                        >
                            {{ ucfirst(str_replace('_', ' ', $reservation->status)) }}
                        </span>
                    </div>

                </div>

            </div>

            <div class="mt-6 rounded-xl border border-yellow-200 bg-yellow-50 p-4">

                <p class="text-sm font-semibold text-yellow-800">
                    Please save your reference number.
                </p>

                <p class="mt-1 text-sm text-yellow-700">
                    You will need it to track the status of your reservation.
                </p>

            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">

                <a
                    href="{{ route('reservation.create') }}"
                    class="rounded-lg border border-blue-700 px-5 py-3 font-semibold text-blue-700 hover:bg-blue-50"
                >
                    Submit Another Request
                </a>

                <a
                    href="{{ route('home') }}"
                    class="rounded-lg bg-blue-700 px-5 py-3 font-semibold text-white hover:bg-blue-800"
                >
                    Return to Home
                </a>

            </div>

        </main>

    </div>

@endsection
