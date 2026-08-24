@extends('layouts.app')

@section('title', 'New Reservation')

@section('content')
<div class="mx-auto max-w-6xl">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-blue-600">Administration</p>

            <h1 class="text-3xl font-bold text-slate-900">
                New Reservation
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Enter the reservation details and requested equipment.
            </p>
        </div>

        <a
            href="{{ route('admin.reservations.index') }}"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
        >
            Back to Reservations
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
            <p class="font-semibold text-red-700">
                Please correct the following errors:
            </p>

            <ul class="mt-2 list-inside list-disc text-sm text-red-600">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.reservations.store') }}"
        class="space-y-6"
    >
        @csrf

        @include('admin.reservations._form')

        <div class="flex justify-end gap-3">
            <a
                href="{{ route('admin.reservations.index') }}"
                class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
                Save Reservation
            </button>
        </div>
    </form>

</div>
@endsection
