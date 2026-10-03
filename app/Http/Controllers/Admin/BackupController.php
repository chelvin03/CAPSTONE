<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BackupRecord;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): View
    {
        $records = BackupRecord::query()->with('creator')->latest()->paginate(10);
        $lastSuccessful = BackupRecord::query()->whereIn('status', ['ready', 'restored'])->latest('verified_at')->first();
        $totalStorage = BackupRecord::query()->sum('size_bytes');

        return view('admin.backups.index', compact('records', 'lastSuccessful', 'totalStorage'));
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backups->create($request->user()->id);
            AuditLog::record('backup.created', $backup, ['filename' => $backup->filename, 'checksum' => $backup->checksum]);

            return back()->with('success', 'Encrypted backup created and verified successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Backup failed: '.$exception->getMessage());
        }
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'backup_file' => ['required', 'file', 'max:524288'],
        ]);

        try {
            $backup = $this->backups->import($validated['backup_file']->getRealPath(), $request->user()->id);
            AuditLog::record('backup.imported', $backup, ['filename' => $backup->filename]);

            return back()->with('success', 'Backup imported and verified. It is now available for recovery.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'Import rejected: '.$exception->getMessage());
        }
    }

    public function download(BackupRecord $backup): StreamedResponse
    {
        abort_unless(Storage::disk($backup->disk)->exists($backup->path), 404);

        return Storage::disk($backup->disk)->download($backup->path, $backup->filename, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verify(BackupRecord $backup): RedirectResponse
    {
        try {
            $this->backups->verify($backup);
            AuditLog::record('backup.verified', $backup, ['filename' => $backup->filename]);

            return back()->with('success', 'Backup integrity verified successfully.');
        } catch (Throwable $exception) {
            $backup->update(['status' => 'corrupt', 'last_error' => $exception->getMessage()]);
            report($exception);

            return back()->with('error', 'Verification failed: '.$exception->getMessage());
        }
    }

    public function restore(Request $request, BackupRecord $backup): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:RESTORE'],
            'password' => ['required', 'current_password'],
        ], [
            'confirmation.in' => 'Type RESTORE exactly to confirm recovery.',
        ]);

        try {
            $manifest = $this->backups->restore($backup);
            AuditLog::record('backup.restored', $backup, [
                'filename' => $backup->filename,
                'source_created_at' => $manifest['created_at'] ?? null,
            ]);

            return redirect()->route('admin.backups.index')->with('success', 'Recovery completed. Database integrity and uploaded files were restored.');
        } catch (Throwable $exception) {
            $backup->update(['status' => 'restore_failed', 'last_error' => $exception->getMessage()]);
            report($exception);

            return back()->with('error', 'Recovery failed safely: '.$exception->getMessage());
        }
    }

    public function destroy(Request $request, BackupRecord $backup): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', 'in:DELETE']]);
        $filename = $backup->filename;
        AuditLog::record('backup.deleted', $backup, ['filename' => $filename]);
        $this->backups->delete($backup);

        return back()->with('success', 'Backup '.$filename.' deleted.');
    }
}
