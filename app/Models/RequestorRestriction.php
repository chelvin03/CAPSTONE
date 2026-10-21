<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestorRestriction extends Model
{
    protected $fillable = ['email', 'reason', 'expires_at', 'created_by', 'revoked_at', 'revoked_by', 'revocation_reason'];

    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    public static function activeFor(string $email): ?self
    {
        return static::where('email', strtolower(trim($email)))->whereNull('revoked_at')
            ->where('expires_at', '>', now())->first();
    }

    public static function assertCanReserve(string $email): void
    {
        $restriction = static::activeFor($email);
        if ($restriction) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'contact_email' => 'New reservations for this email are temporarily restricted until '
                    .$restriction->expires_at->format('F j, Y g:i A').' ('.config('app.timezone').'). Reason: '
                    .$restriction->reason.' Please contact the administrator for review.',
            ]);
        }
    }
}
