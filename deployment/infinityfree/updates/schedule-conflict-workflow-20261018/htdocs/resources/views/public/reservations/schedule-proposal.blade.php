@extends('layouts.public')
@section('title', 'Review Replacement Schedule')
@section('content')
<main class="mx-auto my-10 max-w-2xl rounded-2xl bg-white p-6 shadow">
    <h1 class="mb-4 text-2xl font-bold">{{ $proposal->kind === 'waiting_list' ? 'Review waiting-list offer' : 'Review replacement schedule' }}</h1>
    @if($proposal->kind === 'waiting_list')<p class="my-3 font-semibold">Waiting-list offer: acceptance will confirm approval after another availability and equipment check. Declining or expiry keeps your request on the waiting list. No slot is held.</p>@endif
    @if(session('success'))<p class="mb-4 rounded bg-green-50 p-3 text-green-800" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="mb-4 rounded bg-red-50 p-3 text-red-700" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <p>Reservation: <strong>{{ $proposal->reservation->reference_number }}</strong></p>
    <p class="my-3">Original: {{ $proposal->original['reservation_date'] }}, {{ $proposal->original['start_time'] }} to {{ $proposal->original['end_time'] === '24:00' ? '12:00 midnight' : $proposal->original['end_time'] }}</p>
    <p class="my-3 font-bold">Proposed: {{ $proposal->target['reservation_date'] }}, {{ $proposal->target['start_time'] }} to {{ $proposal->target['end_time'] === '24:00' ? '12:00 midnight' : $proposal->target['end_time'] }}</p>
    <p>Reason: {{ $proposal->reason }}</p>
    <p class="my-3">Reply deadline: {{ $proposal->expires_at->format('F j, Y g:i A') }} ({{ config('app.timezone') }}).</p>
    @if($proposal->status === 'pending' && $current && $proposal->expires_at->isFuture())
        <p class="my-4">Your original booking remains unchanged while you decide. The proposed slot is not held. We recheck availability, cleaning time and equipment when you accept. Declining or expiry leaves your original booking unchanged. {{ $proposal->kind === 'waiting_list' ? 'For this waiting-list offer, successful acceptance confirms approval.' : 'Acceptance changes the schedule only; any existing approval requirements still apply.' }}</p>
        <form method="POST" action="{{ request()->fullUrl() }}" class="flex flex-wrap gap-3">@csrf
            <button name="decision" value="accepted" class="rounded bg-blue-700 px-5 py-3 font-semibold text-white">{{ $proposal->kind === 'waiting_list' ? 'Accept offered slot' : 'Accept replacement schedule' }}</button>
            <button name="decision" value="declined" class="rounded border border-slate-400 px-5 py-3">Decline proposal</button>
        </form>
    @else
        <p class="my-4 font-semibold">Proposal: {{ !$current && $proposal->status === 'pending' ? 'No longer applicable: the reservation has changed' : ($proposal->status === 'pending' ? 'Expired' : ucfirst($proposal->status)) }}.</p>
        <p>Contact the administrator if you need another schedule.</p>
    @endif
    <a class="mt-6 inline-block text-blue-700 underline" href="{{ route('reservation.track', ['reference' => $proposal->reservation->reference_number]) }}">Track your reservation</a>
</main>
@endsection
