<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoginCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, LoginCodeService $codes): RedirectResponse
    {
        // Password validation does not create an authenticated session.
        $request->session()->forget('login_challenge');
        $user = $request->authenticate();
        $codes->assertAllowed($user);
        $request->session()->regenerate();

        // Explicit local test accounts do not have real inboxes.
        $localTestAccount = ($user->role === 'admin' && $user->email === 'admin@mcst.edu.ph'
                && config('gym.auth.local_test_admin_password_only'))
            || ($user->role === 'staff' && $user->email === 'staff@mcst.edu.ph'
                && config('gym.auth.local_test_staff_password_only'));
        if ($user->role === 'admin' || (app()->environment('local') && $localTestAccount)) {
            Auth::guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->route($user->role === 'admin' ? 'admin.dashboard' : 'staff.dashboard');
        }

        $token = (string) Str::uuid();
        $codes->send($user, $token);
        $request->session()->put('login_challenge', [
            'user_id' => $user->id,
            'token' => $token,
            'fingerprint' => $codes->fingerprint($user),
            'remember' => $request->boolean('remember'),
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);

        return redirect()->route('login.code')->with('status', 'A six-digit login code has been sent to your email.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
