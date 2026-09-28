@extends('layouts.app')

@section('title', 'Audit Trail')

@section('content')
<div class="page-shell">
    <div class="page-header">
        <div><h1 class="page-title">Audit Trail</h1><p class="page-description">Tamper-evident accountability history across critical system modules.</p></div>
        <a href="{{ route('admin.audit.export', request()->query()) }}" class="btn-secondary"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Export CSV</a>
    </div>

    <div class="mb-6 rounded-xl border p-4 {{ $integrity['valid'] ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-300 bg-red-50 text-red-900' }}">
        <div class="flex items-center gap-3"><i class="bi {{ $integrity['valid'] ? 'bi-shield-check' : 'bi-shield-exclamation' }} text-2xl" aria-hidden="true"></i><div><p class="font-bold">{{ $integrity['valid'] ? 'Audit integrity verified' : 'Audit integrity warning' }}</p><p class="text-sm">{{ $integrity['valid'] ? number_format($integrity['checked']).' records form a valid cryptographic hash chain. Unauthorized changes or deletion would break this chain.' : 'Integrity verification failed at audit record #'.$integrity['failed_id'].'. Investigate immediately.' }}</p></div></div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5"><p class="text-sm font-semibold text-slate-500">Total records</p><p class="mt-2 text-2xl font-bold">{{ number_format($summary['total']) }}</p></div>
        <div class="card p-5"><p class="text-sm font-semibold text-slate-500">Activities today</p><p class="mt-2 text-2xl font-bold">{{ number_format($summary['today']) }}</p></div>
        <div class="card p-5"><p class="text-sm font-semibold text-slate-500">Accountable actors</p><p class="mt-2 text-2xl font-bold">{{ number_format($summary['actors']) }}</p></div>
        <div class="card p-5"><p class="text-sm font-semibold text-slate-500">Failed logins (7 days)</p><p class="mt-2 text-2xl font-bold {{ $summary['failed_logins'] ? 'text-red-600' : '' }}">{{ number_format($summary['failed_logins']) }}</p></div>
    </div>

    <form method="GET" class="card mb-6 p-5" role="search">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2"><label for="audit_search" class="field-label">Search</label><input id="audit_search" name="search" value="{{ request('search') }}" class="field" placeholder="Actor, action, record, or description"></div>
            <div><label for="audit_module" class="field-label">Module</label><select id="audit_module" name="module" class="field"><option value="">All modules</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ str($module)->headline() }}</option>@endforeach</select></div>
            <div><label for="audit_event" class="field-label">Event</label><select id="audit_event" name="event" class="field"><option value="">All events</option>@foreach(['created','updated','deleted','login','logout','failed','approved','rejected','cancelled','restored','verified'] as $event)<option value="{{ $event }}" @selected(request('event') === $event)>{{ str($event)->headline() }}</option>@endforeach</select></div>
            <div><label for="audit_user" class="field-label">User</label><select id="audit_user" name="user_id" class="field"><option value="">All users</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->full_name }} ({{ $user->email }})</option>@endforeach</select></div>
            <div class="grid grid-cols-2 gap-2"><div><label for="date_from" class="field-label">From</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="field"></div><div><label for="date_to" class="field-label">To</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}" class="field"></div></div>
        </div>
        <div class="mt-4 flex justify-end gap-3">@if(request()->query())<a href="{{ route('admin.audit.index') }}" class="btn-secondary">Clear</a>@endif<button class="btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Apply Filters</button></div>
    </form>

    <section class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Date & Actor</th><th class="px-5 py-3">Activity</th><th class="px-5 py-3">Affected Record</th><th class="px-5 py-3">Request</th><th class="px-5 py-3 text-right">Details</th></tr></thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($logs as $log)
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-5 py-4"><p class="font-semibold text-slate-900">{{ $log->created_at->format('M d, Y g:i:s A') }}</p><p class="mt-1 text-slate-600">{{ $log->actor_name ?? 'System process' }}</p><p class="text-xs text-slate-400">{{ $log->actor_email }}{{ $log->actor_role ? ' · '.str($log->actor_role)->headline() : '' }}</p></td>
                        <td class="px-5 py-4"><span class="status-badge bg-blue-100 text-blue-800">{{ str($log->module)->headline() }}</span><p class="mt-2 font-semibold text-slate-900">{{ str_replace('.', ' / ', $log->action) }}</p><p class="mt-1 max-w-sm text-xs text-slate-500">{{ $log->description }}</p></td>
                        <td class="whitespace-nowrap px-5 py-4 text-slate-700">@if($log->auditable_type)<span class="font-semibold">{{ class_basename($log->auditable_type) }}</span><br><span class="text-xs text-slate-500">Record #{{ $log->auditable_id }}</span>@else<span class="text-slate-400">System-wide</span>@endif</td>
                        <td class="px-5 py-4 text-xs text-slate-500"><p>{{ $log->http_method }} {{ $log->route }}</p><p class="mt-1">IP: {{ $log->ip_address ?? 'N/A' }}</p><p class="mt-1 font-mono" title="{{ $log->request_id }}">Request: {{ $log->request_id ? substr($log->request_id, 0, 8).'...' : 'N/A' }}</p></td>
                        <td class="px-5 py-4 text-right"><details class="relative inline-block text-left"><summary class="btn-secondary !min-h-9 cursor-pointer list-none !px-3 !py-1.5">Inspect</summary><div class="absolute right-0 z-20 mt-2 w-[min(36rem,80vw)] rounded-xl border border-slate-200 bg-white p-5 text-left shadow-xl"><dl class="space-y-3 text-xs"><div><dt class="font-bold text-slate-700">Transaction ID</dt><dd class="mt-1 break-all font-mono text-slate-500">{{ $log->transaction_id ?? 'N/A' }}</dd></div><div><dt class="font-bold text-slate-700">Previous values</dt><dd class="mt-1 max-h-36 overflow-auto rounded-lg bg-slate-50 p-3 font-mono text-slate-600">{{ $log->old_values ? json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'None' }}</dd></div><div><dt class="font-bold text-slate-700">New values / transaction details</dt><dd class="mt-1 max-h-36 overflow-auto rounded-lg bg-slate-50 p-3 font-mono text-slate-600">{{ $log->new_values ? json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'None' }}</dd></div><div><dt class="font-bold text-slate-700">Integrity hash</dt><dd class="mt-1 break-all font-mono text-slate-500">{{ $log->entry_hash }}</dd></div><div><dt class="font-bold text-slate-700">User agent</dt><dd class="mt-1 break-all text-slate-500">{{ $log->user_agent ?? 'N/A' }}</dd></div></dl></div></details></td>
                    </tr>
                @empty<tr><td colspan="5" class="px-6 py-12 text-center text-slate-500"><i class="bi bi-journal-x text-3xl"></i><p class="mt-2 font-semibold">No audit records match these filters.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="border-t border-slate-200 p-4">{{ $logs->links() }}</div>@endif
    </section>
</div>
@endsection
