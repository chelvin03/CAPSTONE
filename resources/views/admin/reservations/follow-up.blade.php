@extends('layouts.app')
@section('title', 'Reservation Follow-up')
@section('content')
<div class="mx-auto max-w-6xl">
    <h1 class="text-2xl font-bold">Reservation follow-up and waiting-list offers</h1>
    <p class="my-4">Review each reminder before sending. Upcoming approved events within 24 hours, supporting documents missing after 24 hours, and unanswered replacement proposals older than 24 hours appear here. Waiting-list offers become eligible for a reminder after 12 hours. Sending a reminder does not change status. This page works without a hosting scheduler.</p>
    @if(session('success'))<p class="my-3 rounded bg-green-50 p-3 text-green-800" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="my-3 rounded bg-red-50 p-3 text-red-700" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($followUps as $followUp)<article class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="font-bold">{{ $followUp['title'] }}</h2>
            <p class="my-2"><a class="text-blue-700 underline" href="{{ route('admin.reservations.show', $followUp['reservation']) }}">{{ $followUp['reservation']->reference_number }}</a> — {{ $followUp['recipient'] }}</p>
            <p class="my-3 text-sm">{{ $followUp['message'] }}</p>
            <p class="text-sm">Delivery: {{ $followUp['delivery']?->status ?? 'Not sent' }}</p>
            <form method="POST" action="{{ route('admin.reservations.follow-up.send') }}" class="mt-3">@csrf
                @foreach(['kind', 'id', 'fingerprint'] as $followField)<input type="hidden" name="{{ $followField }}" value="{{ $followUp[$followField] }}">@endforeach
                <button class="rounded bg-blue-700 px-4 py-2 font-semibold text-white disabled:opacity-50" @disabled($followUp['delivery'] && ($followUp['delivery']->status !== 'failed' || $followUp['delivery']->attempted_at->gt(now()->subHour())))>Send this reminder</button>
            </form>
        </article>@empty<p>No reminders currently due.</p>@endforelse
    </div>
    <section class="my-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="text-lg font-bold">Waiting-list review</h2>
        <p class="my-3">Review priority, submission time, eligibility and available schedules. Open a request to send an offer; the request stays waiting until the requestor accepts and availability passes another check. Offers expire after 24 hours or at their start, whichever comes first. No slot is held and no automatic promotion occurs.</p>
        @forelse($waitingRequests as $waitingRequest)<p class="my-2"><a class="text-blue-700 underline" href="{{ route('admin.reservations.show', $waitingRequest) }}#conflict-resolution">Review / offer {{ $waitingRequest->reference_number }}</a> — requested {{ $waitingRequest->reservation_date->toDateString() }}, {{ substr($waitingRequest->start_time, 0, 5) }}–{{ substr($waitingRequest->end_time, 0, 5) }}; submitted {{ $waitingRequest->created_at->format('M j, Y g:i A') }}; priority {{ $waitingRequest->priority_number ?? 'not assigned' }}.</p>@empty<p>No upcoming waiting-list requests.</p>@endforelse
    </section>
    <section class="my-6 rounded-xl border border-slate-200 bg-white p-5"><h2 class="text-lg font-bold">Approved equipment allocations needing review</h2>
        @forelse($equipmentShortages as $shortage)<p class="my-3"><a class="text-blue-700 underline" href="{{ route('admin.reservations.show', $shortage['reservation']) }}">{{ $shortage['reservation']->reference_number }}</a>: {{ $shortage['equipment']->equipment_name }} — {{ $shortage['equipment']->pivot->quantity_approved }} approved, {{ $shortage['available'] }} currently available after usable stock and overlapping allocations. Review equipment arrangements and notify the requestor; the booking is not automatically cancelled.</p>@empty<p>No upcoming allocation shortages detected.</p>@endforelse
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="text-lg font-bold">Recent reminder delivery</h2>
        @forelse($reminderHistory as $delivery)<p class="my-2 text-sm">{{ $delivery->reservation?->reference_number }} — {{ $delivery->kind }} — {{ $delivery->status }} — {{ $delivery->attempted_at->format('M j, Y g:i A') }}; attempt {{ $delivery->attempts }}.</p>@empty<p>No reminders have been sent.</p>@endforelse
    </section>
</div>
@endsection
