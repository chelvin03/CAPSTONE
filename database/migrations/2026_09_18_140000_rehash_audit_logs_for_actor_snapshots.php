<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rehash();
    }

    public function down(): void
    {
        $this->rehash(true);
    }

    private function rehash(bool $includeUserId = false): void
    {
        $previousHash = str_repeat('0', 64);
        DB::table('audit_logs')->orderBy('id')->get()->each(function (object $log) use (&$previousHash, $includeUserId): void {
            $payload = [
                'id' => $log->id,
                'actor_name' => $log->actor_name,
                'actor_email' => $log->actor_email,
                'actor_role' => $log->actor_role,
                'action' => $log->action,
                'module' => $log->module,
                'event' => $log->event,
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'description' => $log->description,
                'old_values' => $log->old_values ? json_decode($log->old_values, true) : null,
                'new_values' => $log->new_values ? json_decode($log->new_values, true) : null,
                'transaction_id' => $log->transaction_id,
                'request_id' => $log->request_id,
                'http_method' => $log->http_method,
                'route' => $log->route,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => (string) $log->created_at,
            ];
            if ($includeUserId) {
                $payload = ['id' => $payload['id'], 'user_id' => $log->user_id] + array_slice($payload, 1, null, true);
            }
            $entryHash = hash_hmac('sha256', $previousHash.'|'.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (string) config('app.key'));
            DB::table('audit_logs')->where('id', $log->id)->update(['previous_hash' => $previousHash, 'entry_hash' => $entryHash]);
            $previousHash = $entryHash;
        });
    }
};
