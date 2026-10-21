@extends('layouts.app')
@section('title', 'Requestor cancellation review')
@section('content')
<div class="space-y-6 p-6">
    <h1 class="text-2xl font-bold">Requestor cancellation review</h1>
    <a href="{{ route('admin.dashboard') }}" class="text-blue-700 underline">Back to dashboard</a>
    @if(session('success'))<p role="status" class="rounded bg-green-50 p-4">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded bg-red-50 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <p>Review requests submitted during the selected window. Three or more confirmed requestor cancellations flag an email for review; restrictions are never automatic. Administrator cancellations and records without explicit requestor attribution are excluded.</p>
    <form method="GET"><label>Submission window <select name="days" onchange="this.form.submit()">@foreach([30,90,180,365] as $days)<option value="{{ $days }}" @selected($reviewDays === $days)>Last {{ $days }} days</option>@endforeach</select></label></form>
    <p>{{ $reviewFrom->format('M j, Y') }} to {{ now()->format('M j, Y') }}. Cancellation rate = confirmed requestor cancellations ÷ all requests submitted by that email in this window. Requests submitted before this window are excluded from both counts. Email matching ignores case; a different email is a separate identity.</p>
    <div class="overflow-x-auto"><table class="w-full border-collapse text-left"><thead><tr><th class="p-3">Verified email</th><th class="p-3">Requests</th><th class="p-3">Requestor cancellations</th><th class="p-3">Rate</th><th class="p-3">Review</th></tr></thead><tbody>
    @forelse($reviewRows as $row)
        <tr class="border-t"><td class="p-3">{{ $row['email'] }}</td><td class="p-3">{{ $row['total'] }}</td><td class="p-3">{{ $row['cancelled']->count() }}</td><td class="p-3">{{ $row['rate'] }}%</td><td class="p-3">{{ $row['flagged'] ? 'Flagged for review (3+)' : 'Below review threshold' }}
            <details><summary>Supporting cancellations</summary><ul>@foreach($row['cancelled'] as $booking)
                <li class="my-3"><a class="text-blue-700 underline" href="{{ route('admin.reservations.show', $booking) }}">{{ $booking->reference_number }}</a> — Event: {{ $booking->reservation_date->format('M j, Y') }} {{ substr($booking->start_time, 0, 5) }}. Cancelled: {{ $booking->cancelled_at?->format('M j, Y g:i A') ?? 'See status history' }}. Reason: {{ $booking->cancellation_reason ?: 'No reason recorded' }}</li>
            @endforeach</ul></details>
            <details><summary>Apply temporary restriction</summary>
                <form method="POST" action="{{ route('admin.requestor-restrictions.store') }}" class="space-y-3" onsubmit="return confirm('Restrict new reservations for this email until the selected expiry date?');">@csrf
                    <input type="hidden" name="email" value="{{ $row['email'] }}">
                    <label class="block">Reason<textarea name="reason" required maxlength="2000" class="block w-full rounded border-slate-300"></textarea></label>
                    <label class="block">Expiry date (end of day, {{ config('app.timezone') }})<input type="date" name="expires_at" required min="{{ now()->addDay()->toDateString() }}" max="{{ now()->addYear()->toDateString() }}"></label>
                    <button class="rounded bg-red-700 px-4 py-2 text-white">Apply restriction</button>
                </form>
            </details>
        </td></tr>
    @empty<tr><td colspan="5" class="p-4">No confirmed requestor cancellations for this submission window.</td></tr>@endforelse
    </tbody></table></div>
    <h2 class="text-xl font-bold">Restriction history and current status</h2>
    <p>Restrictions prevent new public reservation submissions only. Existing reservations, tracking and cancellation remain available. Expired restrictions stop applying automatically.</p>
    @forelse($restrictions as $restriction)
        <article class="space-y-2 rounded border p-4">
            <h3 class="font-semibold">{{ $restriction->email }} — {{ $restriction->revoked_at ? 'Released' : ($restriction->expires_at->isFuture() ? 'Active' : 'Expired') }}</h3>
            <p>Reason: {{ $restriction->reason }}. Expires: {{ $restriction->expires_at->format('M j, Y g:i A') }} ({{ config('app.timezone') }}).</p>
            @if(!$restriction->revoked_at && $restriction->expires_at->isFuture())
                <form method="POST" action="{{ route('admin.requestor-restrictions.destroy', $restriction) }}">@csrf @method('DELETE')
                    <label>Reason for release <input name="revocation_reason" required maxlength="2000" class="rounded border-slate-300"></label>
                    <button class="rounded bg-blue-700 px-4 py-2 text-white">Release restriction</button>
                </form>
            @elseif($restriction->revoked_at)<p>Release reason: {{ $restriction->revocation_reason }}</p>@endif
        </article>
    @empty<p>No restrictions recorded.</p>@endforelse
</div>
@endsection
