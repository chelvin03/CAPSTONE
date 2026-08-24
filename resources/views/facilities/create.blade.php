@extends('layouts.app')

@section('title', 'Add Facility')

@section('content')
<div class="mx-auto max-w-3xl">

    <div class="mb-6">
        <a
            href="{{ route('admin.facilities.index') }}"
            class="text-sm font-semibold text-blue-600 hover:underline"
        >
            ← Back to Facilities
        </a>

        <h1 class="mt-3 text-3xl font-bold text-slate-900">
            Add Facility
        </h1>

        <p class="mt-1 text-sm text-slate-500">
            Create a new facility record.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
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

    <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">

        <form
            method="POST"
            action="{{ route('admin.facilities.store') }}"
        >
            @csrf

            @include('facilities._form')

            <div class="mt-6 flex justify-end gap-3">
                <a
                    href="{{ route('admin.facilities.index') }}"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="min-h-11 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white transition hover:bg-blue-700"
                >
                    Save Facility
                </button>
            </div>
        </form>

    </div>

</div>
@endsection
