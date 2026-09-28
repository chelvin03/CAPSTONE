<?php

namespace App\Services;

use App\Mail\LoginVerificationCode;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\ExceptionInterface as MailerException;

class LoginCodeService
{
    public function assertAllowed(User $user): void
    {
        if ($user->status !== 'approved') {
            throw ValidationException::withMessages(['email' => match ($user->status) {
                'pending' => 'Your account is still pending administrator approval.',
                'rejected' => 'Your account registration was rejected.',
                'disabled' => 'Your account has been disabled.',
                default => 'Your account is currently unavailable.',
            }]);
        }

        if (! in_array($user->role, ['admin', 'staff'], true)) {
            throw ValidationException::withMessages([
                'email' => 'This account does not have permission to access the system.',
            ]);
        }
    }

    public function fingerprint(User $user): string
    {
        return hash('sha256', $user->email.'|'.$user->getAuthPassword());
    }

    public function send(User $user, string $token): void
    {
        $this->locked($user->id, function () use ($user, $token) {
            $this->checkLimit('attempts', $user->id, 5);
            $this->checkLimit('cooldown', $user->id, 1);
            $this->checkLimit('sends', $user->id, 5);

            // SMTP only: a log/failover mailer would persist the plaintext code.
            if (config('mail.mailers.smtp.transport') !== 'smtp') {
                throw ValidationException::withMessages(['code' => 'Login email delivery is not configured. Please contact the administrator.']);
            }

            RateLimiter::hit($this->key('cooldown', $user->id), 60);
            RateLimiter::hit($this->key('sends', $user->id), 600);
            $previous = Cache::get($this->key('challenge', $user->id));
            do {
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while ($previous && Hash::check($this->pepper($code), $previous['hash']));

            Cache::put($this->key('challenge', $user->id), [
                'token' => $token,
                'hash' => Hash::make($this->pepper($code)),
                'expires_at' => now()->addMinutes(10)->timestamp,
            ], 600);

            try {
                // Send synchronously so no plaintext code is serialized into a queue job.
                Mail::mailer('smtp')->to($user->email)->send(new LoginVerificationCode($code));
            } catch (MailerException|\InvalidArgumentException $exception) {
                Cache::forget($this->key('challenge', $user->id));
                // Transport exceptions may contain message contents; never log them here.
                throw ValidationException::withMessages(['code' => 'We could not send your login code. Please wait one minute and try again, or contact the administrator.']);
            }
        });
    }

    public function verify(User $user, string $token, string $code): void
    {
        $this->locked($user->id, function () use ($user, $token, $code) {
            $this->checkLimit('attempts', $user->id, 5);
            $challenge = Cache::get($this->key('challenge', $user->id));

            if (! $challenge || ! hash_equals($challenge['token'], $token) || $challenge['expires_at'] <= now()->timestamp) {
                throw ValidationException::withMessages(['code' => 'Your code has expired or was replaced. Request a new code.']);
            }

            if (! Hash::check($this->pepper($code), $challenge['hash'])) {
                RateLimiter::hit($this->key('attempts', $user->id), 600);
                $remaining = RateLimiter::remaining($this->key('attempts', $user->id), 5);
                if ($remaining === 0) {
                    Cache::forget($this->key('challenge', $user->id));
                }
                throw ValidationException::withMessages(['code' => $remaining > 0
                    ? "The code is incorrect. You have {$remaining} attempt(s) remaining."
                    : 'Too many incorrect codes. Please wait 10 minutes before trying again.']);
            }

            // Consume under the same lock as verification to prevent code replay.
            Cache::forget($this->key('challenge', $user->id));
            RateLimiter::clear($this->key('attempts', $user->id));
        });
    }

    public function forget(int $userId, string $token): void
    {
        $this->locked($userId, function () use ($userId, $token) {
            $challenge = Cache::get($this->key('challenge', $userId));
            if ($challenge && hash_equals($challenge['token'], $token)) {
                Cache::forget($this->key('challenge', $userId));
            }
        });
    }

    private function checkLimit(string $action, int $userId, int $maximum): void
    {
        $key = $this->key($action, $userId);
        if (RateLimiter::tooManyAttempts($key, $maximum)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['code' => "Too many code requests or attempts. Please try again in {$seconds} second(s)."]);
        }
    }

    private function locked(int $userId, callable $callback): void
    {
        try {
            Cache::lock($this->key('lock', $userId), 60)->block(3, $callback);
        } catch (LockTimeoutException $exception) {
            throw ValidationException::withMessages(['code' => 'A login verification request is already in progress. Please try again shortly.']);
        }
    }

    private function key(string $action, int $userId): string
    {
        return "login-code:{$action}:{$userId}";
    }

    private function pepper(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }
}
