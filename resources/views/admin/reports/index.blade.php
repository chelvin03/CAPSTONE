@extends('layouts.app')

@section('title', 'Submitted Reports')

@section('content')
<div class="mx-auto max-w-7xl">
    <div class="mb-6">
        <p class="text-sm font-semibold text-blue-600">Administration</p>
        <h1 class="text-3xl font-bold text-slate-900">Staff Reports</h1>
        <p class="mt-1 text-sm text-slate-500">Review weekly, monthly, and annual analytics submitted by staff.</p>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-dashboard-stat title="Reservations" :value="$reservationTotal" detail="All time" icon="&#128197;" icon-class="bg-blue-50" />
        <x-dashboard-stat title="Cancellation Rate" :value="$cancellationRate . '%'" detail="All reservations" icon="&#10005;" icon-class="bg-rose-50" />
        <x-dashboard-stat title="Priority Overrides" :value="$priorityOverrides" detail="Bump history" icon="&#9889;" icon-class="bg-amber-50" />
        <x-dashboard-stat title="Peak Date" :value="$peakDate ? \Carbon\Carbon::parse($peakDate->reservation_date)->format('M d, Y') : '—'" detail="Most reservations" icon="&#128200;" icon-class="bg-violet-50" />
    </div>

    <div class="mb-5 text-right"><a href="{{route('admin.reports.export')}}" class="inline-flex rounded-lg bg-slate-900 px-5 py-3 text-sm font-semibold text-white">Export Analytics CSV</a></div>

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900">Generate Database Report</h2>
        <p class="mt-1 text-sm text-slate-500">Select the report and optional filters, then export the same database results to Excel or Word.</p>
        @if ($errors->any())<div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
        <form method="GET" class="mt-5 grid gap-4 md:grid-cols-3" id="report-generator">
            <label class="text-sm font-semibold">Report<select name="report_type" required class="mt-2 w-full rounded-lg border-slate-300"><option value="reservation_detail">Reservation Detail</option><option value="summary">Reservation Summary</option><option value="priority_history">Priority Override History</option></select></label>
            <label class="text-sm font-semibold">From<input type="date" name="date_from" class="mt-2 w-full rounded-lg border-slate-300"></label>
            <label class="text-sm font-semibold">To<input type="date" name="date_to" class="mt-2 w-full rounded-lg border-slate-300"></label>
            <label class="text-sm font-semibold">Status<select name="status" class="mt-2 w-full rounded-lg border-slate-300"><option value="">All statuses</option>@foreach(['new','pending','validated','waiting_list','approved','rejected','cancelled','completed'] as $status)<option value="{{$status}}">{{ucwords(str_replace('_',' ',$status))}}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Category<select name="category" class="mt-2 w-full rounded-lg border-slate-300"><option value="">All categories</option>@foreach($categories as $category)<option value="{{$category}}">{{ucwords($category)}}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Created By<select name="user_id" class="mt-2 w-full rounded-lg border-slate-300"><option value="">All users</option>@foreach($reportUsers as $user)<option value="{{$user->id}}">{{$user->full_name}}</option>@endforeach</select></label>
            <div class="md:col-span-3 flex flex-col justify-end gap-3 sm:flex-row">
                <button type="submit" formaction="{{route('admin.reports.excel')}}" class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white">Export Excel (.xlsx)</button>
                <button type="submit" formaction="{{route('admin.reports.word')}}" class="rounded-lg bg-blue-600 px-5 py-3 font-semibold text-white">Export Word (.docx)</button>
            </div>
        </form>
    </section>

    <div class="mb-5 flex flex-wrap gap-2">
        @foreach (['' => 'All Reports', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'annual' => 'Annual'] as $value => $label)
            <a href="{{ route('admin.reports.index', array_filter(['type' => $value])) }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $type === $value ? 'bg-blue-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50"><tr>
                    @foreach (['Type', 'Submitted By', 'Reporting Period', 'Reservations', 'Submitted', ''] as $heading)
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $heading }}</th>
                    @endforeach
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($reports as $report)
                        <tr>
                            <td class="px-6 py-4"><span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800">{{ ucfirst($report->report_type) }}</span></td>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $report->submitter->full_name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $report->period_start->format('M d, Y') }} – {{ $report->period_end->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ number_format($report->total_reservations) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $report->submitted_at->format('M d, Y g:i A') }}</td>
                            <td class="px-6 py-4 text-right"><a href="{{ route('admin.reports.show', $report) }}" class="text-sm font-semibold text-blue-700 hover:underline">View Report</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No staff reports have been submitted.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($reports->hasPages())<div class="border-t border-slate-200 p-5">{{ $reports->links() }}</div>@endif
    </div>
</div>
@endsection
