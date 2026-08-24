@extends('layouts.app')

@section('content')
<div class="page-shell">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                Reservation Management
            </h1>
            <p class="page-description">
                Manage all gym reservation requests.
            </p>
        </div>

        <a href="{{ route('admin.reservations.create') }}"
           class="btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> New Reservation
        </a>
    </div>

    <form method="GET" class="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-[minmax(12rem,1fr)_12rem_auto_auto]" role="search">

        <input
            type="text"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search reference, event, or facility"
            aria-label="Search reservations"
            class="field"
        >

        <select
            name="status"
            aria-label="Filter by status"
            class="field"
        >
            <option value="">All Status</option>

            @foreach(['new','approved','rejected','cancelled','completed'] as $item)
                <option
                    value="{{ $item }}"
                    @selected(request('status')==$item)
                >
                    {{ ucfirst(str_replace('_',' ',$item)) }}
                </option>
            @endforeach
        </select>

        <button
            class="btn-primary">
            <i class="bi bi-search" aria-hidden="true"></i> Search
        </button>

        @if(request()->filled('search') || request()->filled('status'))
            <a href="{{ route('admin.reservations.index') }}" class="btn-secondary">Clear</a>
        @endif

    </form>

    <div class="card overflow-x-auto">

        <table class="min-w-full">

            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left">Reference</th>
                    <th class="px-4 py-3 text-left">Facility</th>
                    <th class="px-4 py-3 text-left">Event</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-center">Action</th>
                </tr>
            </thead>

            <tbody>

            @forelse($reservations as $reservation)

                <tr class="border-t">

                    <td class="px-4 py-3">
                        {{ $reservation->reference_number }}
                    </td>

                    <td class="px-4 py-3">
                        {{ $reservation->facility->facility_name }}
                    </td>

                    <td class="px-4 py-3">
                        {{ $reservation->event_name }}
                    </td>

                    <td class="px-4 py-3">
                        {{ $reservation->reservation_date->format('M d, Y') }}
                    </td>

                    <td class="px-4 py-3">
                        <span class="status-badge {{ match($reservation->status) { 'approved', 'completed' => 'bg-emerald-100 text-emerald-800', 'rejected', 'cancelled' => 'bg-red-100 text-red-800', default => 'bg-amber-100 text-amber-800' } }}">
                            {{ ucfirst($reservation->status) }}
                        </span>
                    </td>

                    <td class="px-4 py-3 text-center">

                        <a
                            href="{{ route('admin.reservations.show',$reservation) }}"
                            class="btn-primary !min-h-9 !px-3 !py-1.5"
                            aria-label="View reservation {{ $reservation->reference_number }}"
                        >
                            View
                        </a>

                    </td>

                </tr>

            @empty

                <tr>

                    <td colspan="6"
                        class="py-8 text-center text-gray-500">

                        No reservations found.

                    </td>

                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    <div class="mt-6">
        {{ $reservations->links() }}
    </div>

</div>
@endsection
