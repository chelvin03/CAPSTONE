@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<link rel="stylesheet" href="{{ asset('css/admin-reports.css') }}?v={{ filemtime(public_path('css/admin-reports.css')) }}">
<div class="admin-reports mx-auto max-w-7xl space-y-6">
    @include('admin.reports.partials.dashboard')
    @if ($data)
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="results-title">
            <div class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><h2 id="results-title" class="text-lg font-semibold text-[#1E3A8A]">{{ $data['title'] }}</h2><p class="mt-1 text-sm text-slate-500">{{ $filters['date_from'] }} to {{ $filters['date_to'] }} · Generated {{ $data['generatedAt']->format('M d, Y g:i A') }}</p></div>
                    <div class="flex flex-wrap gap-2"><a class="btn-secondary" href="{{ route('admin.reports.pdf', $filters) }}">Download PDF</a><a class="btn-primary bg-[#2563EB]" href="{{ route('admin.reports.excel', $filters) }}">Export Excel</a></div>
                </div>
                <dl class="mt-5 flex flex-wrap gap-x-10 gap-y-4">@foreach ($data['metrics'] as $label => $value)<div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 text-xl font-semibold text-[#1E3A8A]">{{ is_numeric($value) ? number_format($value, is_float($value) ? 2 : 0) : $value }}</dd></div>@endforeach</dl>
                <p class="mt-4 text-xs leading-relaxed text-slate-500">{{ $data['note'] }}</p>
            </div>
            @if ($data['total'] === 0)<p role="status" class="border-t border-slate-200 p-5 text-sm text-slate-500">No reservations match the selected period.</p>@endif
            <div class="overflow-x-auto"><table class="w-full text-left text-sm">
                <caption class="sr-only">{{ $data['title'] }} for {{ $filters['date_from'] }} to {{ $filters['date_to'] }}</caption>
                <thead class="border-y border-slate-200 bg-slate-50 text-slate-600"><tr>@foreach ($data['headers'] as $heading)<th scope="col" class="whitespace-nowrap px-5 py-3 font-semibold">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($data['rows'] ?? $records as $record)
                        <tr>@foreach ($data['rows'] !== null ? $record : $service->reportRow($record) as $cell)<td class="px-5 py-3 text-slate-700">{{ $cell }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($data['headers']) }}" class="p-8 text-center text-slate-500">No records to display.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @if ($records && $records->hasPages())<div class="border-t border-slate-200 p-5">{{ $records->links() }}</div>@endif
        </section>
    @endif
</div>
@endsection
