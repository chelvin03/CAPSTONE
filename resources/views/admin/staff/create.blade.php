@extends('layouts.app')

@section('title', 'Add Staff')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <a href="{{ route('admin.staff.index') }}" class="text-sm font-semibold text-blue-600 hover:underline">← Back to Staff</a>
        <h1 class="mt-3 text-3xl font-bold text-slate-900">Add Staff Account</h1>
        <p class="mt-1 text-sm text-slate-500">Create a verified account that the staff member can use immediately.</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
        <form method="POST" action="{{ route('admin.staff.store') }}">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label for="first_name" class="mb-2 block text-sm font-semibold">First Name</label><input id="first_name" name="first_name" value="{{ old('first_name') }}" required class="w-full rounded-lg border-slate-300"><x-input-error :messages="$errors->get('first_name')" class="mt-2" /></div>
                <div><label for="last_name" class="mb-2 block text-sm font-semibold">Last Name</label><input id="last_name" name="last_name" value="{{ old('last_name') }}" required class="w-full rounded-lg border-slate-300"><x-input-error :messages="$errors->get('last_name')" class="mt-2" /></div>
                <div><label for="email" class="mb-2 block text-sm font-semibold">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-lg border-slate-300"><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
                <div><label for="contact_number" class="mb-2 block text-sm font-semibold">Contact Number</label><input id="contact_number" name="contact_number" value="{{ old('contact_number') }}" class="w-full rounded-lg border-slate-300"><x-input-error :messages="$errors->get('contact_number')" class="mt-2" /></div>
                <div><label for="password" class="mb-2 block text-sm font-semibold">Password</label><input id="password" type="password" name="password" required autocomplete="new-password" class="w-full rounded-lg border-slate-300"><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
                <div><label for="password_confirmation" class="mb-2 block text-sm font-semibold">Confirm Password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-lg border-slate-300"></div>
            </div>
            <div class="mt-7 flex justify-end gap-3">
                <a href="{{ route('admin.staff.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                <button class="min-h-11 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700">Create Staff Account</button>
            </div>
        </form>
    </div>
</div>
@endsection
