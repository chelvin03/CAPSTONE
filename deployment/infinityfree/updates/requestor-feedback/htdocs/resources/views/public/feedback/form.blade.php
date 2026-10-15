@extends('layouts.public')
@section('title', 'Give Feedback')
@section('content')
<div class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-2xl font-bold">How Was Your Gym Experience?</h1>
    <p class="mt-3 text-slate-600">{{ $reservation->reference_number }} · {{ $reservation->reservation_date->format('M j, Y') }}</p>
    <form method="POST" action="{{ $submitUrl }}" class="mt-6 space-y-5">
        @csrf
        <fieldset><legend class="font-semibold">Overall Rating *</legend><p class="mt-1 text-sm text-slate-500">1 = Poor · 2 = Fair · 3 = Good · 4 = Very Good · 5 = Excellent</p><div class="mt-3 flex flex-wrap gap-4">@foreach(range(1,5) as $rating)<label class="flex items-center gap-2"><input type="radio" name="rating" value="{{ $rating }}" @checked((int)old('rating') === $rating) required>{{ $rating }} ★</label>@endforeach</div>@error('rating')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror</fieldset>
        <div><label for="comments" class="block font-semibold">Comments or Suggestions (optional)</label><textarea id="comments" name="comments" rows="5" maxlength="3000" class="mt-2 w-full rounded-lg border-slate-300" placeholder="Tell us what went well and what we can improve.">{{ old('comments') }}</textarea>@error('comments')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <p class="text-sm text-slate-500">Your feedback is sent to the gym administrator and is not displayed publicly.</p>
        <button class="landing-button" type="submit">Submit Feedback</button>
    </form>
</div>
@endsection
