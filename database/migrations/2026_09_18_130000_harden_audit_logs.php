<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('module', 100)->nullable()->after('action')->index();
            $table->string('event', 50)->nullable()->after('module')->index();
            $table->string('actor_name')->nullable()->after('user_id');
            $table->string('actor_email')->nullable()->after('actor_name');
            $table->string('actor_role', 50)->nullable()->after('actor_email');
            $table->uuid('transaction_id')->nullable()->after('description')->index();
            $table->uuid('request_id')->nullable()->after('transaction_id')->index();
            $table->string('http_method', 10)->nullable()->after('request_id');
            $table->text('route')->nullable()->after('http_method');
            $table->string('previous_hash', 64)->nullable()->after('user_agent');
            $table->string('entry_hash', 64)->nullable()->after('previous_hash')->unique();
        });

        $previousHash = str_repeat('0', 64);
        DB::table('audit_logs')->orderBy('id')->get()->each(function (object $log) use (&$previousHash): void {
            $actionParts = explode('.', (string) $log->action);
            $payload = [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'actor_name' => null,
                'actor_email' => null,
                'actor_role' => null,
                'action' => $log->action,
                'module' => $actionParts[0] ?: 'system',
                'event' => end($actionParts) ?: 'event',
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'description' => $log->description,
                'old_values' => $log->old_values ? json_decode($log->old_values, true) : null,
                'new_values' => $log->new_values ? json_decode($log->new_values, true) : null,
                'transaction_id' => null,
                'request_id' => null,
                'http_method' => null,
                'route' => null,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => (string) $log->created_at,
            ];
            $entryHash = hash_hmac('sha256', $previousHash.'|'.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (string) config('app.key'));
            DB::table('audit_logs')->where('id', $log->id)->update([
                'module' => $payload['module'],
                'event' => $payload['event'],
                'previous_hash' => $previousHash,
                'entry_hash' => $entryHash,
            ]);
            $previousHash = $entryHash;
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropUnique(['entry_hash']);
            $table->dropIndex(['module']);
            $table->dropIndex(['event']);
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['request_id']);
            $table->dropColumn(['module', 'event', 'actor_name', 'actor_email', 'actor_role', 'transaction_id', 'request_id', 'http_method', 'route', 'previous_hash', 'entry_hash']);
        });
    }
};
