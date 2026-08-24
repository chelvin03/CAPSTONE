@extends('layouts.app')

@section('title', 'Facilities Management')

@section('content')
<div class="mx-auto max-w-7xl">

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <p class="text-sm font-semibold text-blue-600">
                Administration
            </p>

            <h1 class="text-3xl font-bold text-slate-900">
                Facilities Management
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Add, update, search, and manage facility records.
            </p>
        </div>

        <a
            href="{{ route('admin.facilities.create') }}"
            class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
        >
            + Add Facility
        </a>

    </div>

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 p-5">

            <form
                method="GET"
                action="{{ route('admin.facilities.index') }}"
                class="flex flex-col gap-3 sm:flex-row"
            >
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search by name, location, or status"
                    class="min-h-11 flex-1 rounded-lg border border-slate-300 px-4 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                >

                <button
                    type="submit"
                    class="min-h-11 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white transition hover:bg-slate-800"
                >
                    Search
                </button>

                @if ($search !== '')
                    <a
                        href="{{ route('admin.facilities.index') }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Clear
                    </a>
                @endif
            </form>

        </div>

        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-slate-200">

                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Facility
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Location
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Capacity
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Status
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 bg-white">

                    @forelse ($facilities as $facility)

                        <tr>

                            <td class="px-5 py-4">

                                <p class="font-semibold text-slate-900">
                                    {{ $facility->facility_name }}
                                </p>

                                @if ($facility->description)
                                    <p class="mt-1 max-w-md text-xs text-slate-500">
                                        {{ $facility->description }}
                                    </p>
                                @endif

                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $facility->location ?? 'Not specified' }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ number_format($facility->capacity) }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    @class([
                                        'rounded-full px-3 py-1 text-xs font-semibold',
                                        'bg-green-100 text-green-700' => $facility->status === 'available',
                                        'bg-red-100 text-red-700' => $facility->status === 'unavailable',
                                        'bg-amber-100 text-amber-700' => $facility->status === 'maintenance',
                                        'bg-slate-100 text-slate-700' => ! in_array(
                                            $facility->status,
                                            ['available', 'unavailable', 'maintenance'],
                                            true
                                        ),
                                    ])
                                >
                                    {{ ucfirst($facility->status) }}
                                </span>
                            </td>

                            <td class="px-5 py-4">

                                <div class="flex justify-end gap-2">

                                    <a
                                        href="{{ route('admin.facilities.edit', $facility) }}"
                                        class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route('admin.facilities.destroy', $facility) }}"
                                        onsubmit="return confirm('Delete this facility? Facilities used by reservations cannot be deleted.');"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-red-700"
                                        >
                                            Delete
                                        </button>
                                    </form>

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">
                                No facility records found.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if ($facilities->hasPages())
            <div class="border-t border-slate-200 p-5">
                {{ $facilities->links() }}
            </div>
        @endif

    </div>

</div>
@endsection
