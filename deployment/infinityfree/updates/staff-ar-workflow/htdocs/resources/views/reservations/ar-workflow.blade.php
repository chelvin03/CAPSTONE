@extends('layouts.app')
@section('title', 'Prepare Acknowledgement Receipt')
@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold">Acknowledgement Receipt (AR)</h1>
    <p class="mt-2 text-slate-600">{{ $reservation->reference_number }} · {{ $reservation->event_name }}</p>
    <div class="card mt-6 p-6">
        <p class="font-semibold">{{ !$receipt ? 'Approved / For AR — awaiting staff preparation' : (!$isCurrent ? 'Booking changed — staff must prepare the AR again' : ($receipt->sent_at ? 'AR sent to requestor' : 'AR prepared — ready to send')) }}</p>
        @if($errors->any())<p class="mt-4 text-red-700" role="alert">{{ $errors->first() }}</p>@endif
        @if($isCurrent)
            <p class="mt-3 text-sm text-slate-600">Prepared by {{ $receipt->snapshot['prepared_name'] }} on {{ $receipt->prepared_at->format('M j, Y g:i A') }}.</p>
            @if($receipt->sent_at)<p class="mt-2 text-sm text-green-800">Sent to {{ $receipt->sent_to }} on {{ $receipt->sent_at->format('M j, Y g:i A') }}.</p>@endif
            <a class="btn-secondary mt-4" href="{{ route($isStaff ? 'staff.reservations.receipt.print' : 'admin.reservations.receipt.print', $reservation) }}" target="_blank" rel="noopener">Review / Print Prepared AR</a>
        @endif
        @if($isStaff)
            <form method="POST" action="{{ route('staff.reservations.receipt.prepare', $reservation) }}" class="mt-6 space-y-4">@csrf
                <label class="block font-semibold" for="ar-notes">Staff Notes (optional)</label>
                <textarea id="ar-notes" name="notes" maxlength="2000" rows="3" class="w-full rounded-lg border-slate-300" placeholder="Additional release notes for the requestor">{{ old('notes', $receipt?->notes) }}</textarea>
                <button class="btn-primary" type="submit">{{ $receipt ? 'Save / Prepare AR Again' : 'Prepare AR' }}</button>
            </form>
            @if($isCurrent)
                <form method="POST" action="{{ route('staff.reservations.receipt.send', $reservation) }}" class="mt-5" onsubmit="return confirm('Send this prepared AR to the requestor booking email?');">@csrf
                    <input type="hidden" name="version" value="{{ $receipt->version }}">
                    <p class="mb-3 text-sm text-slate-600">Email to: {{ $reservation->contact_email ?: 'No booking email recorded — ask the administrator to update it.' }}</p>
                    <button class="btn-primary" type="submit" @disabled(!$reservation->contact_email)>{{ $receipt->sent_at ? 'Resend AR to Requestor' : 'Send AR to Requestor' }}</button>
                </form>
            @endif
        @else
            <p class="mt-5 text-sm text-slate-600">Staff prepares and sends the AR after approval. You can review the prepared receipt here.</p>
        @endif
    </div>
    <a class="mt-5 inline-block text-blue-700 underline" href="{{ route($isStaff ? 'staff.reservations.show' : 'admin.reservations.show', $reservation) }}">Back to Reservation</a>
</div>
@endsection
