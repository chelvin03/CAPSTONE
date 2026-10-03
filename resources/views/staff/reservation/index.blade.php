@extends('layouts.app')
@section('title', 'Staff Reservations')
@section('content')
<div class="page-shell">
    <header class="page-header"><div><p class="text-sm font-semibold text-blue-700">Staff Monitoring</p><h1 class="page-title">Reservations</h1><p class="page-description">Read-only operational records, ordered by scheduled date and start time.</p></div></header>
    @if ($errors->any())<div class="alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <form class="card mb-6 grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4" method="GET" action="{{ route('staff.reservations.index') }}">
        <label class="text-sm font-semibold">Reference or event name<input class="field mt-2" type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100"></label>
        <label class="text-sm font-semibold">Scheduled date<input class="field mt-2" type="date" name="date" value="{{ $filters['date'] ?? '' }}"></label>
        <label class="text-sm font-semibold">Status<select class="field mt-2" name="status"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></label>
        <div class="flex flex-wrap items-end gap-2"><button class="btn-primary" type="submit">Apply filters</button><a class="btn-secondary" href="{{ route('staff.reservations.index') }}">Reset</a></div>
    </form>
    <section class="card overflow-hidden"><p class="border-b border-slate-100 p-5 text-sm text-slate-600">{{ $reservations->total() }} matching reservations</p>
        @include('staff.partials.reservations-table', ['rows' => $reservations, 'caption' => 'Read-only reservations', 'emptyMessage' => 'No reservation records match these filters.'])
        @if ($reservations->hasPages())<div class="border-t p-5">{{ $reservations->links() }}</div>@endif
    </section>
</div>
@endsection
