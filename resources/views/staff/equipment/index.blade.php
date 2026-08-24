@extends('layouts.app')

@section('title', 'Staff Equipment')

@section('content')
<div class="mx-auto max-w-7xl">

    <div class="mb-6">
        <p class="text-sm font-semibold text-blue-600">
            Staff Monitoring
        </p>

        <h1 class="text-3xl font-bold text-slate-900">
            Equipment
        </h1>

        <p class="mt-1 text-sm text-slate-500">
            View-only equipment records.
        </p>
    </div>

    <form
        method="GET"
        action="{{ route('staff.equipment.index') }}"
        class="mb-5 flex gap-3"
    >
        <input
            type="text"
            name="search"
            value="{{ $search ?? '' }}"
            placeholder="Search equipment name, unit, or status"
            class="flex-1 rounded-lg border border-slate-300 px-4 py-2"
        >

        <button
            type="submit"
            class="rounded-lg bg-slate-900 px-5 py-2 font-semibold text-white"
        >
            Search
        </button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <table class="min-w-full divide-y divide-slate-200">

            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                        Equipment
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                        Quantity
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                        Unit
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                        Status
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">

                @forelse ($equipment as $item)

                    <tr>
                        <td class="px-5 py-4 text-sm font-semibold text-slate-800">
                            {{ $item->equipment_name }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-700">
                            {{ number_format($item->total_quantity) }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-700">
                            {{ ucfirst($item->unit) }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-700">
                            {{ ucfirst($item->status) }}
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-slate-500">
                            No equipment records found.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

        @if ($equipment->hasPages())
            <div class="border-t border-slate-200 p-5">
                {{ $equipment->links() }}
            </div>
        @endif

    </div>

</div>
@endsection
