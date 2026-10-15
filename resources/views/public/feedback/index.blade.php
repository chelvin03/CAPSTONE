@extends('layouts.public')
@section('title', 'Gymnasium Feedback')
@section('content')
<div class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-2xl font-bold">Share Your Feedback</h1>
    <p class="mt-3 text-sm text-slate-600">After your approved gym session ends, enter your reservation reference and the email used for your booking. We will email a secure link so you can rate your experience.</p>
    @if(session('status'))<p class="mt-4 rounded-lg bg-green-50 p-4 text-green-800" role="status">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('public.feedback.request') }}" class="mt-6 space-y-5">
        @csrf
        <div><label for="reference_number" class="block font-semibold">Reservation Reference</label><input id="reference_number" name="reference_number" value="{{ old('reference_number') }}" required maxlength="50" class="mt-2 w-full rounded-lg border-slate-300">@error('reference_number')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="email" class="block font-semibold">Booking Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" class="mt-2 w-full rounded-lg border-slate-300">@error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <button type="submit" class="landing-button">Send Feedback Link</button>
    </form>
</div>
@endsection
