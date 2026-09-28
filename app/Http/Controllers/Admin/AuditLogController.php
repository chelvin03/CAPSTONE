<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);
        $logs = (clone $query)->with('user')->latest('id')->paginate(25)->withQueryString();
        $integrity = AuditLog::verifyChain();
        $modules = AuditLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
        $users = User::query()->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'))->orderBy('first_name')->get();
        $summary = [
            'total' => AuditLog::query()->count(),
            'today' => AuditLog::query()->whereDate('created_at', today())->count(),
            'failed_logins' => AuditLog::query()->where('action', 'authentication.failed')->where('created_at', '>=', now()->subDays(7))->count(),
            'actors' => AuditLog::query()->whereNotNull('actor_email')->distinct()->count('actor_email'),
        ];

        return view('admin.audit.index', compact('logs', 'integrity', 'modules', 'users', 'summary'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'audit-trail-'.now()->format('Y-m-d_His').'.csv';
        $query = $this->filteredQuery($request)->with('user')->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['ID', 'Date/Time', 'Actor', 'Email', 'Role', 'Action', 'Module', 'Affected Record', 'Description', 'Old Values', 'New Values', 'IP Address', 'Request ID', 'Transaction ID', 'Integrity Hash']);
            $query->chunkById(500, function ($logs) use ($handle): void {
                foreach ($logs as $log) {
                    fputcsv($handle, [$log->id, $log->created_at?->toIso8601String(), $log->actor_name ?? 'System', $log->actor_email, $log->actor_role, $log->action, $log->module, class_basename((string) $log->auditable_type).' #'.$log->auditable_id, $log->description, json_encode($log->old_values), json_encode($log->new_values), $log->ip_address, $log->request_id, $log->transaction_id, $log->entry_hash]);
                }
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredQuery(Request $request): Builder
    {
        return AuditLog::query()
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->input('search'));
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_email', 'like', "%{$search}%")
                        ->orWhere('auditable_type', 'like', "%{$search}%")
                        ->orWhere('auditable_id', $search);
                });
            })
            ->when($request->filled('module'), fn (Builder $query) => $query->where('module', $request->input('module')))
            ->when($request->filled('user_id'), fn (Builder $query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('event'), fn (Builder $query) => $query->where('event', $request->input('event')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->input('date_to')));
    }
}
