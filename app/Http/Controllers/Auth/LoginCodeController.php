<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginCodeController extends Controller
{
    public function show(Request $request, LoginCodeService $codes): View|RedirectResponse
    {
        $user = $this->pendingUser($request, $codes);

        return $user instanceof RedirectResponse ? $user : view('auth.login-code');
    }

    public function verify(Request $request, LoginCodeService $codes): RedirectResponse
    {
        $user = $this->pendingUser($request, $codes);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $validated = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        $challenge = $request->session()->get('login_challenge');
        $codes->verify($user, $challenge['token'], $validated['code']);

        $request->session()->forget('login_challenge');
        Auth::guard('web')->login($user, $challenge['remember']);
        $request->session()->regenerate();

        return redirect()->route($user->role === 'admin' ? 'admin.dashboard' : 'staff.dashboard');
    }

    public function resend(Request $request, LoginCodeService $codes): RedirectResponse
    {
        $user = $this->pendingUser($request, $codes);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $codes->send($user, $request->session()->get('login_challenge.token'));

        return redirect()->route('login.code')->with('status', 'A new code has been sent. Use the most recent email.');
    }

    public function cancel(Request $request, LoginCodeService $codes): RedirectResponse
    {
        $challenge = $request->session()->get('login_challenge');
        if ($challenge) {
            $codes->forget($challenge['user_id'], $challenge['token']);
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function pendingUser(Request $request, LoginCodeService $codes): User|RedirectResponse
    {
        $challenge = $request->session()->get('login_challenge');
        $user = $challenge ? User::find($challenge['user_id']) : null;
        if (! $user || $challenge['expires_at'] <= now()->timestamp
            || ! hash_equals($challenge['fingerprint'], $codes->fingerprint($user))) {
            $request->session()->forget('login_challenge');

            return redirect()->route('login')->withErrors(['email' => 'Your login session has expired. Please sign in again.']);
        }

        try {
            $codes->assertAllowed($user);
        } catch (ValidationException $exception) {
            $codes->forget($user->id, $challenge['token']);
            $request->session()->forget('login_challenge');

            return redirect()->route('login')->withErrors($exception->errors());
        }

        return $user;
    }
}
