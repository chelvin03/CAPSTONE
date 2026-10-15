@extends('layouts.app')
@section('title', 'Requestor Feedback')
@section('content')
<div class="page-shell">
    <div class="page-header"><div><h1 class="page-title">Requestor Feedback</h1><p class="page-description">Ratings and comments after requestors use the gymnasium.</p></div></div>
    <p class="mb-5 text-slate-600">{{ number_format($total) }} responses · Average rating: <strong>{{ $average === null ? 'N/A' : number_format($average, 1).' / 5' }}</strong></p>
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3"><label for="rating">Rating</label><select id="rating" name="rating" class="rounded-lg border-slate-300"><option value="">All ratings</option>@foreach(range(1,5) as $rating)<option value="{{ $rating }}" @selected((int)request('rating') === $rating)>{{ $rating }} / 5</option>@endforeach</select><button class="btn-primary" type="submit">Filter</button></form>
    <div class="space-y-4">@forelse($feedback as $entry)
        <article class="card p-6"><div class="flex flex-wrap justify-between gap-3"><div><h2 class="font-bold">{{ $entry->rating }} / 5 ★</h2><a class="text-blue-700 underline" href="{{ route('admin.reservations.show', $entry->reservation_id) }}">{{ $entry->reservation?->reference_number }}</a><p class="mt-1 text-sm text-slate-600">{{ $entry->reservation?->contact_person }} · {{ $entry->reservation?->event_name }}</p></div><time class="text-sm text-slate-500">{{ $entry->created_at->format('M j, Y g:i A') }}</time></div><p class="mt-4 whitespace-pre-wrap break-words text-slate-700">{{ $entry->comments ?: 'No comments provided.' }}</p></article>
    @empty<p class="card p-6 text-slate-500">No feedback received for this selection.</p>@endforelse</div>
    <div class="mt-6">{{ $feedback->links() }}</div>
</div>
@endsection
