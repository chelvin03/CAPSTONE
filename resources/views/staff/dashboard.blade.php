@extends('layouts.app')

@section('title', 'Staff Dashboard')

@section('content')

    <div class="mx-auto max-w-6xl">

        {{-- Dashboard Header --}}
        <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">

            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">

                <div class="flex items-center gap-5">

                    <img
                        src="{{ asset('images/mcst-logo.png') }}"
                        alt="MCST Logo"
                        class="h-20 w-20 rounded-full object-contain"
                    >

                    <div>
                        <p class="text-sm font-bold text-blue-700">
                            MCST Gymnasium
                        </p>

                        <h1 class="mt-1 text-3xl font-bold text-slate-900">
                            Staff Dashboard
                        </h1>

                        <p class="mt-2 text-slate-500">
                            Welcome, {{ auth()->user()->first_name ?? 'Staff' }}
                            {{ auth()->user()->last_name ?? '' }}.
                        </p>
                    </div>

                </div>

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="rounded-lg bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                    >
                        Logout
                    </button>
                </form>

            </div>

        </div>

        {{-- View-Only Notice --}}
        <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4">

            <p class="font-semibold text-blue-900">
                Staff access is view-only.
            </p>

            <p class="mt-1 text-sm text-blue-700">
                Staff can view reservations, facilities, and equipment but
                cannot approve, reject, cancel, add, edit, or delete records.
            </p>

        </div>

        {{-- Dashboard Cards --}}
        <div class="grid gap-5 md:grid-cols-3">

            {{-- Reservations --}}
            <a
                href="{{ route('staff.reservations.index') }}"
                class="group block rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-blue-300 hover:shadow-md"
            >
                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Reservations
                        </p>

                        <p class="mt-3 text-lg font-bold text-slate-900">
                            View only
                        </p>
                    </div>

                    <div class="rounded-xl bg-blue-100 p-3 text-2xl">
                        📅
                    </div>

                </div>

                <p class="mt-4 text-sm leading-6 text-slate-500">
                    View reservation records, schedules, requestor details, and
                    reservation statuses.
                </p>

                <p class="mt-5 text-sm font-semibold text-blue-700">
                    Open Reservations →
                </p>
            </a>

            {{-- Facilities --}}
            <a
                href="{{ route('staff.facilities.index') }}"
                class="group block rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-blue-300 hover:shadow-md"
            >
                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Facilities
                        </p>

                        <p class="mt-3 text-lg font-bold text-slate-900">
                            View only
                        </p>
                    </div>

                    <div class="rounded-xl bg-blue-100 p-3 text-2xl">
                        🏢
                    </div>

                </div>

                <p class="mt-4 text-sm leading-6 text-slate-500">
                    View facility names, descriptions, locations, capacities,
                    and availability statuses.
                </p>

                <p class="mt-5 text-sm font-semibold text-blue-700">
                    Open Facilities →
                </p>
            </a>

            {{-- Equipment --}}
            <a
                href="{{ route('staff.equipment.index') }}"
                class="group block rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-blue-300 hover:shadow-md"
            >
                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Equipment
                        </p>

                        <p class="mt-3 text-lg font-bold text-slate-900">
                            View only
                        </p>
                    </div>

                    <div class="rounded-xl bg-blue-100 p-3 text-2xl">
                        🧰
                    </div>

                </div>

                <p class="mt-4 text-sm leading-6 text-slate-500">
                    View equipment names, descriptions, quantities, units, and
                    availability statuses.
                </p>

                <p class="mt-5 text-sm font-semibold text-blue-700">
                    Open Equipment →
                </p>
            </a>

        </div>

    </div>
@endsection
