<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
{
    $this->ensureIsNotRateLimited();

    $login = Str::lower(trim((string) $this->input('email')));

    if (! Auth::attempt([
        'email' => $login,
        'password' => $this->input('password'),
    ], $this->boolean('remember'))) {
        RateLimiter::hit($this->throttleKey());

        $remainingAttempts = RateLimiter::remaining(
            $this->throttleKey(),
            5
        );

        throw ValidationException::withMessages([
            'email' => $remainingAttempts > 0
                ? 'The email or password is incorrect. You have '
                    .$remainingAttempts.' login attempt(s) remaining.'
                : 'Too many failed login attempts. Please wait one minute before trying again.',
        ]);
    }

    RateLimiter::clear($this->throttleKey());
}

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'Too many failed login attempts. Please try again in '
                .$seconds.' second(s).',
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
