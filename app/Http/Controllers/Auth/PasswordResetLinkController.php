<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $admin = \App\Models\User::where('email', $request->input('email'))
            ->where('role', 'admin')->whereNotNull('recovery_email')->first();

        if ($admin && $admin->recovery_email !== '') {
            Password::sendResetLink(['email' => $admin->email, 'role' => 'admin']);
        }

        return back()->with('status', 'If an admin account with a recovery email matches, a password reset link has been sent.');
    }
}
