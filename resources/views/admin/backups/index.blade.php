@extends('layouts.app')

@section('title', 'Backup & Recovery')

@section('content')
<div class="page-shell">
    <div class="page-header">
        <div>
            <h1 class="page-title">Backup & Recovery</h1>
            <p class="page-description">Create, verify, download, and restore encrypted system recovery points.</p>
        </div>
        <form method="POST" action="{{ route('admin.backups.store') }}">
            @csrf
            <button class="btn-primary" type="submit"><i class="bi bi-shield-plus" aria-hidden="true"></i> Create Full Backup</button>
        </form>
    </div>

    @if (session('success'))<div class="alert-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>{{ session('success') }}</span></div>@endif
    @if (session('error'))<div class="alert-error"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>{{ session('error') }}</span></div>@endif
    @if ($errors->any())<div class="alert-error"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><div><p class="font-semibold">The request could not be completed.</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <section class="card p-5"><p class="text-sm font-semibold text-slate-500">Last verified backup</p><p class="mt-2 text-xl font-bold text-slate-900">{{ $lastSuccessful?->verified_at?->format('M d, Y g:i A') ?? 'None yet' }}</p><p class="mt-1 text-xs text-slate-500">Integrity checked with SHA-256</p></section>
        <section class="card p-5"><p class="text-sm font-semibold text-slate-500">Stored recovery points</p><p class="mt-2 text-xl font-bold text-slate-900">{{ $records->total() }}</p><p class="mt-1 text-xs text-slate-500">Manual, scheduled, and imported backups</p></section>
        <section class="card p-5"><p class="text-sm font-semibold text-slate-500">Backup storage used</p><p class="mt-2 text-xl font-bold text-slate-900">{{ number_format($totalStorage / 1048576, 2) }} MB</p><p class="mt-1 text-xs text-slate-500">Retention: {{ config('gym.backup.retention_days') }} days</p></section>
    </div>

    <section class="card mb-6 p-6">
        <div class="grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-center">
            <div><h2 class="text-lg font-bold text-slate-900">Import an encrypted backup</h2><p class="mt-1 text-sm text-slate-600">Use a downloaded <code>.mcstbak</code> file from this installation. It is decrypted and fully verified before it can be restored.</p></div>
            <form method="POST" action="{{ route('admin.backups.import') }}" enctype="multipart/form-data" class="space-y-3">@csrf<input type="file" name="backup_file" required accept=".mcstbak,application/octet-stream" class="field text-sm"><button type="submit" class="btn-secondary w-full"><i class="bi bi-upload" aria-hidden="true"></i> Import & Verify</button></form>
        </div>
    </section>

    <section class="card overflow-hidden">
        <div class="border-b border-slate-200 px-6 py-5"><h2 class="text-lg font-bold text-slate-900">Recovery Points</h2><p class="mt-1 text-sm text-slate-500">Each archive includes application database records, uploaded documents, a manifest, and per-file checksums.</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Backup</th><th class="px-5 py-3">Contents</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Size</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($records as $backup)
                    <tr>
                        <td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $backup->filename }}</p><p class="mt-1 text-xs text-slate-500">{{ $backup->created_at->format('M d, Y g:i A') }} · {{ ucfirst($backup->type) }} · {{ $backup->creator?->name ?? 'System' }}</p><p class="mt-1 font-mono text-[0.68rem] text-slate-400" title="{{ $backup->checksum }}">SHA-256: {{ substr($backup->checksum, 0, 16) }}...</p></td>
                        <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ number_format($backup->table_count) }} tables<br>{{ number_format($backup->row_count) }} rows · {{ number_format($backup->file_count) }} files</td>
                        <td class="px-5 py-4"><span class="status-badge {{ in_array($backup->status, ['ready', 'restored']) ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">{{ str_replace('_', ' ', $backup->status) }}</span>@if($backup->verified_at)<p class="mt-1 text-xs text-slate-500">Verified {{ $backup->verified_at->diffForHumans() }}</p>@endif</td>
                        <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ number_format($backup->size_bytes / 1048576, 2) }} MB</td>
                        <td class="px-5 py-4"><div class="flex justify-end gap-2">
                            <a href="{{ route('admin.backups.download', $backup) }}" class="btn-secondary !min-h-9 !px-3 !py-1.5" title="Download"><i class="bi bi-download" aria-hidden="true"></i></a>
                            <form method="POST" action="{{ route('admin.backups.verify', $backup) }}">@csrf<button class="btn-secondary !min-h-9 !px-3 !py-1.5" title="Verify integrity"><i class="bi bi-shield-check" aria-hidden="true"></i></button></form>
                            <button type="button" onclick="document.getElementById('restore-backup-{{ $backup->id }}').showModal()" class="btn-primary !min-h-9 !px-3 !py-1.5">Restore</button>
                            <button type="button" onclick="document.getElementById('delete-backup-{{ $backup->id }}').showModal()" class="btn !min-h-9 !bg-red-50 !px-3 !py-1.5 !text-red-700 hover:!bg-red-100" title="Delete"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </div>
                    <dialog id="restore-backup-{{ $backup->id }}" class="w-[min(42rem,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/60">
                        <form method="POST" action="{{ route('admin.backups.restore', $backup) }}" class="p-6 text-left">
                            @csrf
                            <div class="flex items-start gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xl text-amber-700"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
                                <div><h2 class="text-xl font-bold text-slate-900">Confirm system recovery</h2><p class="mt-1 break-all text-sm font-semibold text-slate-600">{{ $backup->filename }}</p></div>
                            </div>
                            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Current application data and uploaded files will be replaced with this recovery point. A file rollback point is created automatically if recovery fails.</div>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div><label class="field-label" for="restore-confirmation-{{ $backup->id }}">Type RESTORE to continue</label><input id="restore-confirmation-{{ $backup->id }}" name="confirmation" required pattern="RESTORE" autocomplete="off" class="field" placeholder="RESTORE"></div>
                                <div><label class="field-label" for="restore-password-{{ $backup->id }}">Current admin password</label><input id="restore-password-{{ $backup->id }}" name="password" type="password" required autocomplete="current-password" class="field"></div>
                            </div>
                            <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="this.closest('dialog').close()" class="btn-secondary">Cancel</button><button type="submit" class="btn !bg-amber-600 !text-white hover:!bg-amber-700"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Confirm Restore</button></div>
                        </form>
                    </dialog>
                    <dialog id="delete-backup-{{ $backup->id }}" class="w-[min(32rem,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/60">
                        <form method="POST" action="{{ route('admin.backups.destroy', $backup) }}" class="p-6 text-left">
                            @csrf
                            @method('DELETE')
                            <h2 class="text-xl font-bold text-slate-900">Delete recovery point?</h2><p class="mt-2 text-sm text-slate-600">This permanently deletes <strong class="break-all">{{ $backup->filename }}</strong> and cannot be undone.</p>
                            <div class="mt-5"><label class="field-label" for="delete-confirmation-{{ $backup->id }}">Type DELETE to continue</label><input id="delete-confirmation-{{ $backup->id }}" name="confirmation" required pattern="DELETE" autocomplete="off" class="field" placeholder="DELETE"></div>
                            <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="this.closest('dialog').close()" class="btn-secondary">Cancel</button><button type="submit" class="btn !bg-red-600 !text-white hover:!bg-red-700">Delete Backup</button></div>
                        </form>
                    </dialog>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500"><i class="bi bi-database text-3xl" aria-hidden="true"></i><p class="mt-2 font-semibold">No recovery points yet</p><p class="text-sm">Create the first full backup using the button above.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($records->hasPages())<div class="border-t border-slate-200 p-4">{{ $records->links() }}</div>@endif
    </section>

    <section class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card p-6"><h2 class="text-lg font-bold text-slate-900"><i class="bi bi-clock-history mr-2 text-blue-600"></i>Schedule & Retention</h2><dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between gap-4"><dt class="text-slate-500">Automatic backup</dt><dd class="font-semibold">Daily at {{ config('gym.backup.schedule_time') }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Retention</dt><dd class="font-semibold">{{ config('gym.backup.retention_days') }} days</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Storage</dt><dd class="font-semibold">Private {{ config('gym.backup.disk') }} disk</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Protection</dt><dd class="font-semibold">Application-key encryption + SHA-256</dd></div></dl><p class="mt-4 rounded-lg bg-blue-50 p-3 text-xs text-blue-800">The server scheduler must run every minute for automatic backups: <code>php artisan schedule:run</code>.</p></div>
        <div class="card p-6"><h2 class="text-lg font-bold text-slate-900"><i class="bi bi-journal-check mr-2 text-blue-600"></i>Recovery Procedure</h2><ol class="mt-4 list-inside list-decimal space-y-2 text-sm text-slate-700"><li>Create and download a recent backup before maintenance.</li><li>Click Verify to confirm the archive is readable and unchanged.</li><li>Select Restore, type <strong>RESTORE</strong>, and confirm your admin password.</li><li>After recovery, sign in again if requested and test reservations and uploaded permits.</li><li>Keep an off-site copy of downloaded backups and protect the application key.</li></ol><p class="mt-4 text-xs text-slate-500">Important: encrypted archives require the same <code>APP_KEY</code>. Store the key separately in a secure password vault.</p></div>
    </section>
</div>
@endsection
