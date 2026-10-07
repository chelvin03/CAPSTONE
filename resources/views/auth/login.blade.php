<x-guest-layout>
    <style>
        body {
            color: #1E293B;
            font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif;
            background: #1E3A8A;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            background-image: linear-gradient(90deg, rgba(15, 23, 42, .46), rgba(15, 23, 42, .55) 55%, rgba(15, 23, 42, .72)), url("{{ asset('images/mcst-gymnasium-exterior.png') }}");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        body > main {
            min-height: 100vh;
            min-height: 100svh;
            justify-content: flex-end;
            padding: 32px 7vw;
        }
        body > main > div { width: 100%; max-width: 430px; }
        .mcst-login-card {
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, .7);
            border-radius: 16px;
            background: rgba(255, 255, 255, .98);
            box-shadow: 0 24px 70px rgba(15, 23, 42, .3);
        }
        .mcst-login-content { padding: 30px 34px 28px; }
        .mcst-login-brand { text-align: center; }
        .mcst-login-brand img { width: 72px; height: 72px; margin: 0 auto 14px; object-fit: contain; }
        .mcst-login-brand h1 { color: #1E3A8A; font-size: 21px; line-height: 1.4; font-weight: 700; letter-spacing: -.4px; }
        .mcst-login-brand p { margin-top: 8px; color: #64748b; font-size: 12px; }
        .mcst-login-notice { margin-top: 22px; padding: 12px 14px; border-left: 3px solid #2563EB; border-radius: 6px; background: #F8FAFC; color: #64748b; font-size: 11px; line-height: 1.7; }
        .mcst-login-notice strong { display: block; color: #1E3A8A; font-weight: 600; margin-bottom: 2px; }
        .mcst-login-form { margin-top: 22px; }
        .mcst-login-field + .mcst-login-field { margin-top: 18px; }
        .mcst-login-field label { display: block; margin-bottom: 7px; font-size: 12px; font-weight: 600; }
        .mcst-login-field input {
            display: block; width: 100%; min-height: 48px; border: 1px solid #cbd5e1;
            border-radius: 8px; background: #fff; padding: 12px 14px; color: #1E293B; font-size: 14px;
            transition: border-color .15s, box-shadow .15s;
        }
        .mcst-login-field input::placeholder { color: #94a3b8; }
        .mcst-login-field input:focus { outline: none; border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37, 99, 235, .12); }
        .mcst-login-password { position: relative; }
        .mcst-login-password input { padding-right: 52px; }
        .mcst-login-toggle {
            position: absolute; right: 3px; top: 3px; width: 44px; height: 42px;
            display: flex; align-items: center; justify-content: center; border-radius: 6px; color: #64748b;
        }
        .mcst-login-toggle:hover { color: #2563EB; background: #F8FAFC; }
        .mcst-login-toggle svg { width: 20px; height: 20px; }
        .mcst-login-options { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin: 18px 0 22px; font-size: 12px; }
        .mcst-login-remember { display: inline-flex; align-items: center; gap: 8px; min-height: 32px; color: #475569; cursor: pointer; }
        .mcst-login-remember input { width: 16px; height: 16px; border-radius: 4px; border-color: #cbd5e1; color: #2563EB; }
        .mcst-login-options a { color: #2563EB; font-weight: 500; padding: 6px 0; }
        .mcst-login-options a:hover { text-decoration: underline; }
        .mcst-login-submit { width: 100%; min-height: 48px; border-radius: 8px; background: #2563EB; color: white; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(37, 99, 235, .18); transition: background .15s; }
        .mcst-login-submit:hover { background: #1E3A8A; }
        .mcst-login-footer { padding: 16px 20px; border-top: 1px solid #e2e8f0; background: #F8FAFC; text-align: center; font-size: 10px; line-height: 1.7; color: #64748b; }
        @media (max-width: 900px) {
            body > main { justify-content: center; padding: 28px 20px; }
            body::before { background-image: linear-gradient(rgba(15, 23, 42, .55), rgba(15, 23, 42, .55)), url("{{ asset('images/mcst-gymnasium-exterior.png') }}"); }
        }
        @media (max-width: 480px) {
            body > main { padding: 20px 16px; }
            .mcst-login-content { padding: 26px 24px; }
            .mcst-login-brand h1 { font-size: 20px; }
        }
    </style>

    <div class="mcst-login-card">
        <div class="mcst-login-content">
            <div class="mcst-login-brand">
                <img src="{{ asset('images/mcst-logo.png') }}" alt="MCST Logo">
                <h1>MCST Gymnasium<br>Reservation System</h1>
                <p>Administrator and Staff Portal</p>
            </div>

            <x-auth-session-status class="mt-6" :status="session('status')" />

            <div class="mcst-login-notice">
                <strong>Authorized access only</strong>
                Only approved administrator and staff accounts can access the system.
                Five failed attempts will temporarily lock login.
            </div>

            @if ($errors->any())
                <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3" role="alert">
                    <p class="mb-1 text-sm font-semibold text-red-800">Login warning</p>
                    <ul class="space-y-1 text-sm text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mcst-login-form">
                @csrf
                <div class="mcst-login-field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                        required autofocus autocomplete="username" placeholder="Enter your email address">
                </div>

                <div class="mcst-login-field">
                    <label for="password">Password</label>
                    <div class="mcst-login-password">
                        <input id="password" type="password" name="password" required
                            autocomplete="current-password" placeholder="Enter your password">
                        <button type="button" id="togglePassword" class="mcst-login-toggle"
                            aria-label="Show password" aria-pressed="false" aria-controls="password">
                            <svg id="passwordEye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                                <circle cx="12" cy="12" r="3"/>
                                <path id="passwordEyeSlash" d="M3 3l18 18" style="display: none"/>
                            </svg>
                        </button>
                    </div>
                    <p id="capsLockWarning" class="mt-2 hidden text-sm font-medium text-amber-700" role="alert">Warning: Caps Lock is on.</p>
                </div>

                <div class="mcst-login-options">
                    <label class="mcst-login-remember">
                        <input id="remember_me" type="checkbox" name="remember">
                        <span>Remember Me</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}">Forgot Password?</a>
                    @endif
                </div>

                <button type="submit" class="mcst-login-submit">Login</button>
            </form>
        </div>
        <div class="mcst-login-footer">
            &copy; {{ now()->year }} MCST Information Systems Department
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('togglePassword');
            const capsLockWarning = document.getElementById('capsLockWarning');

            toggleButton.addEventListener('click', function () {
                const isHidden = passwordInput.type === 'password';
                passwordInput.type = isHidden ? 'text' : 'password';
                toggleButton.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                toggleButton.setAttribute('aria-pressed', String(isHidden));
                document.getElementById('passwordEyeSlash').style.display = isHidden ? '' : 'none';
            });

            passwordInput.addEventListener('keyup', function (event) {
                const capsLockIsOn = event.getModifierState && event.getModifierState('CapsLock');
                capsLockWarning.classList.toggle('hidden', !capsLockIsOn);
            });

            passwordInput.addEventListener('blur', function () {
                capsLockWarning.classList.add('hidden');
            });
        });
    </script>
</x-guest-layout>