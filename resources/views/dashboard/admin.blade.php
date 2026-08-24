@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="mx-auto max-w-7xl">
    <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-medium text-blue-700">Administration</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Dashboard</h1>
            <p class="mt-2 text-sm text-slate-500">
                Welcome back, {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}.
            </p>
        </div>
        <a href="{{ route('admin.reservations.create') }}" class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">
            <span class="mr-2 text-lg leading-none">+</span> New Reservation
        </a>
    </header>

    <section class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-dashboard-stat title="Total Reservations" :value="$totalReservations" :detail="$thisMonthReservations . ' this month'" detail-class="text-emerald-600" icon="&#128197;" icon-class="bg-blue-50" />
        <x-dashboard-stat title="Pending Requests" :value="$pendingReservations" detail="Requires attention" detail-class="text-amber-600" icon="&#9203;" icon-class="bg-amber-50" />
        <x-dashboard-stat title="Approved" :value="$approvedReservations" :detail="$approvalRate . '% approval rate'" detail-class="text-emerald-600" icon="&#10003;" icon-class="bg-emerald-50" />
        <x-dashboard-stat title="Cancelled" :value="$cancelledReservations" :detail="$thisMonthCancellations . ' this month'" detail-class="text-rose-600" icon="&#10005;" icon-class="bg-rose-50" />
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="border-b border-slate-100 px-6 py-5">
                <h2 class="font-semibold text-slate-900">Reservation Overview</h2>
                <p class="mt-1 text-sm text-slate-500">Reservations scheduled during the last 12 months</p>
            </div>
            <div class="p-6">
                <div class="flex h-72 items-end gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 pb-4 pt-8 sm:gap-4">
                    @foreach ($monthlyActivity as $month)
                        <div class="flex h-full min-w-0 flex-1 flex-col justify-end text-center" title="{{ $month['label'] }} {{ $month['year'] }}: {{ $month['count'] }} reservations">
                            <span class="mb-1 text-xs font-semibold text-slate-700">{{ $month['count'] }}</span>
                            <div class="mx-auto w-full max-w-10 rounded-t bg-blue-600 hover:bg-blue-700" style="height: {{ max(4, ($month['count'] / $monthlyMaximum) * 190) }}px"></div>
                            <span class="mt-2 truncate text-xs text-slate-500">{{ $month['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5">
                <h2 class="font-semibold text-slate-900">Quick Summary</h2>
            </div>
            <div class="divide-y divide-slate-100 px-6">
                @foreach ([
                    'Facilities' => $facilityCount,
                    'Available Equipment' => $availableEquipment,
                    'Completed Events' => $completedEvents,
                ] as $label => $value)
                    <div class="flex items-center justify-between py-5">
                        <span class="text-sm text-slate-500">{{ $label }}</span>
                        <span class="font-semibold text-slate-900">{{ number_format($value) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
            <div>
                <h2 class="font-semibold text-slate-900">Recent Reservations</h2>
                <p class="mt-1 text-sm text-slate-500">Latest submitted and updated requests</p>
            </div>
            <a href="{{ route('admin.reservations.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">View all</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach (['Reference', 'Requestor', 'Event', 'Date', 'Status'] as $heading)
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $heading }}</th>
                        @endforeach
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($recentReservations as $reservation)
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $reservation->reference_number }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $reservation->contact_person }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $reservation->event_name }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $reservation->reservation_date->format('F d, Y') }}</td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-amber-100 text-amber-800' => in_array($reservation->status, ['new', 'pending', 'validated', 'waiting_list'], true),
                                    'bg-emerald-100 text-emerald-800' => in_array($reservation->status, ['approved', 'completed'], true),
                                    'bg-rose-100 text-rose-800' => in_array($reservation->status, ['rejected', 'cancelled'], true),
                                ])>{{ ucwords(str_replace('_', ' ', $reservation->status)) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <a href="{{ route('admin.reservations.show', $reservation) }}" class="rounded-lg border border-blue-200 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-50">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No reservations have been submitted yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
