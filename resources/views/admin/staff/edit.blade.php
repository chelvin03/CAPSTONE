@extends('layouts.app')
@section('title', 'Edit Staff Login')
@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="text-3xl font-bold text-slate-900">Edit Staff Login</h1>
    <p class="mt-2 text-slate-600">Update login details for {{ $staff->full_name }}. Use an email inbox the staff member can access for login codes.</p>
    <form method="POST" action="{{ route('admin.staff.update', $staff) }}" class="mt-6 space-y-6 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @method('PUT')
        <div>
            <label for="staff-email" class="block font-semibold">Staff Email</label>
            <input id="staff-email" name="email" type="email" value="{{ old('email', $staff->email) }}" required autocomplete="off" class="mt-2 block w-full rounded-lg border-slate-300">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <label for="staff-password" class="block font-semibold">New Staff Password (optional)</label>
            <p id="staff-password-help" class="mt-1 text-sm text-slate-600">Leave blank to keep the current password. A new password needs at least 12 characters, uppercase and lowercase letters, a number, and a symbol.</p>
            <input id="staff-password" name="password" type="password" minlength="12" autocomplete="new-password" aria-describedby="staff-password-help" class="mt-2 block w-full rounded-lg border-slate-300">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <label for="staff-password-confirmation" class="block font-semibold">Confirm New Staff Password</label>
            <input id="staff-password-confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" class="mt-2 block w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label for="admin-current-password" class="block font-semibold">Your Current Admin Password</label>
            <input id="admin-current-password" name="current_password" type="password" required autocomplete="current-password" class="mt-2 block w-full rounded-lg border-slate-300">
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>
        <div class="flex items-center gap-4">
            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-3 font-semibold text-white">Save Staff Login</button>
            <a href="{{ route('admin.staff.index') }}" class="text-blue-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
