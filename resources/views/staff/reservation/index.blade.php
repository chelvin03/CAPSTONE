@extends('layouts.app')

@section('title', 'Staff Reservations')

@section('content')
<div class="mx-auto max-w-7xl">

    <div class="mb-6">
        <p class="text-sm font-semibold text-blue-600">
            Staff Monitoring
        </p>

        <h1 class="text-3xl font-bold text-slate-900">
            Reservations
        </h1>

        <p class="mt-1 text-sm text-slate-500">
            View reservation records. Staff cannot approve, reject, cancel,
            edit, or delete reservations.
        </p>
    </div>

    <form
        method="GET"
        action="{{ route('staff.reservations.index') }}"
        class="mb-5 flex gap-3"
    >
        <input
            type="text"
            name="search"
            value="{{ $search ?? '' }}"
            placeholder="Search reference, event, contact person, or status"
            class="flex-1 rounded-lg border border-slate-300 px-4 py-2"
        >

        <button
            type="submit"
            class="rounded-lg bg-slate-900 px-5 py-2 font-semibold text-white"
        >
            Search
        </button>

        @if (($search ?? '') !== '')
            <a
                href="{{ route('staff.reservations.index') }}"
                class="rounded-lg border border-slate-300 px-5 py-2 font-semibold text-slate-700"
            >
                Clear
            </a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-slate-200">

                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Reference
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Event
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Facility
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Date
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Status
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">
                            Action
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse ($reservations as $reservation)

                        <tr>
                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $reservation->reference_number }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $reservation->event_name }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $reservation->facility?->facility_name ?? 'Not assigned' }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ optional($reservation->reservation_date)->format('M d, Y') }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ ucwords(str_replace('_', ' ', $reservation->status)) }}
                            </td>

                            <td class="px-5 py-4 text-right">
                                <a
                                    href="{{ route('staff.reservations.show', $reservation) }}"
                                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white"
                                >
                                    View
                                </a>
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-slate-500">
                                No reservation records found.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if ($reservations->hasPages())
            <div class="border-t border-slate-200 p-5">
                {{ $reservations->links() }}
            </div>
        @endif

    </div>

</div>
@endsection
