<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'actor_name', 'actor_email', 'actor_role', 'action', 'module',
        'event', 'auditable_type', 'auditable_id', 'description', 'old_values',
        'new_values', 'transaction_id', 'request_id', 'http_method', 'route',
        'ip_address', 'user_agent', 'previous_hash', 'entry_hash', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit records are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit records cannot be deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public static function record(string $action, Model $subject, array $details = []): void
    {
        static::recordEvent($action, $subject, null, $details);
    }

    public static function recordEvent(
        string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?User $actor = null,
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $actor ??= auth()->user();
        $parts = explode('.', $action);
        $module = $parts[0] ?: 'system';
        $event = end($parts) ?: 'event';
        $request = app()->runningInConsole() ? null : request();
        $createdAt = now();

        DB::transaction(function () use ($action, $subject, $oldValues, $newValues, $description, $actor, $module, $event, $request, $createdAt): void {
            $last = static::query()->lockForUpdate()->latest('id')->first();
            $previousHash = $last?->entry_hash ?? str_repeat('0', 64);
            $actorId = $actor && User::query()->whereKey($actor->getKey())->exists() ? $actor->getKey() : null;
            $attributes = [
                'user_id' => $actorId,
                'actor_name' => $actor?->full_name,
                'actor_email' => $actor?->email,
                'actor_role' => $actor?->role,
                'action' => $action,
                'module' => $module,
                'event' => $event,
                'auditable_type' => $subject?->getMorphClass(),
                'auditable_id' => $subject?->getKey(),
                'description' => $description ?? Str::headline(str_replace('.', ' ', $action)),
                'old_values' => static::redact($oldValues),
                'new_values' => static::redact($newValues),
                'transaction_id' => static::transactionId(),
                'request_id' => $request?->attributes->get('request_id'),
                'http_method' => $request?->method(),
                'route' => $request?->route()?->getName() ?? $request?->path(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'created_at' => $createdAt,
            ];

            $log = new static($attributes);
            $log->previous_hash = $previousHash;
            $log->save();
            $log->entry_hash = static::hashFor($log, $previousHash);
            DB::table('audit_logs')->where('id', $log->id)->update(['entry_hash' => $log->entry_hash]);
        }, 3);
    }

    public static function verifyChain(): array
    {
        $previousHash = str_repeat('0', 64);
        $checked = 0;
        foreach (static::query()->orderBy('id')->cursor() as $log) {
            $checked++;
            $expected = static::hashFor($log, $previousHash);
            if (! hash_equals($previousHash, (string) $log->previous_hash) || ! hash_equals($expected, (string) $log->entry_hash)) {
                return ['valid' => false, 'checked' => $checked, 'failed_id' => $log->id];
            }
            $previousHash = (string) $log->entry_hash;
        }

        return ['valid' => true, 'checked' => $checked, 'failed_id' => null];
    }

    private static function hashFor(self $log, string $previousHash): string
    {
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
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'transaction_id' => $log->transaction_id,
            'request_id' => $log->request_id,
            'http_method' => $log->http_method,
            'route' => $log->route,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
        ];

        return hash_hmac('sha256', $previousHash.'|'.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (string) config('app.key'));
    }

    private static function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        foreach (['password', 'password_confirmation', 'remember_token', 'verification_code'] as $sensitive) {
            if (array_key_exists($sensitive, $values)) {
                $values[$sensitive] = '[REDACTED]';
            }
        }

        return $values;
    }

    private static function transactionId(): string
    {
        if (! app()->runningInConsole()) {
            $request = request();
            if (! $request->attributes->has('audit_transaction_id')) {
                $request->attributes->set('audit_transaction_id', (string) Str::uuid());
            }

            return (string) $request->attributes->get('audit_transaction_id');
        }

        return (string) Str::uuid();
    }
}
