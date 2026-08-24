<x-guest-layout>

    <div class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">

        <div class="px-8 pb-8 pt-10">

            <div class="text-center">

                <img
                    src="{{ asset('images/mcst-logo.png') }}"
                    alt="MCST Logo"
                    class="mx-auto h-20 w-20 rounded-full object-contain"
                >

                <h1 class="mt-5 text-2xl font-bold text-slate-900">
                    MCST Gymnasium
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Reservation and Utilization System
                </p>

            </div>

            <x-auth-session-status
                class="mt-6"
                :status="session('status')"
            />

            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <p class="font-semibold">Authorized access only</p>
                <p class="mt-1">
                    Only approved administrator and staff accounts can access
                    the system. Five failed attempts will temporarily lock login.
                </p>
            </div>

            @if ($errors->any())
                <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                    <p class="mb-1 text-sm font-semibold text-red-800">
                        Login warning
                    </p>
                    <ul class="space-y-1 text-sm text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('login') }}"
                class="mt-7 space-y-5"
            >
                @csrf

                <div>
                    <label
                        for="email"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Email address
                    </label>

                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="Enter your email address"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    >
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">

                        <label
                            for="password"
                            class="block text-sm font-semibold text-slate-700"
                        >
                            Password
                        </label>

                        @if (Route::has('password.request'))
                            <a
                                href="{{ route('password.request') }}"
                                class="text-sm font-medium text-blue-600 hover:text-blue-700 hover:underline"
                            >
                                Forgot Password?
                            </a>
                        @endif

                    </div>

                    <div class="relative">

                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 pr-14 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        >

                        <button
                            type="button"
                            id="togglePassword"
                            class="absolute inset-y-0 right-0 flex items-center px-4 text-sm font-semibold text-slate-500 hover:text-blue-600"
                            aria-label="Show or hide password"
                        >
                            Show
                        </button>

                    </div>

                    <p
                        id="capsLockWarning"
                        class="mt-2 hidden text-sm font-medium text-amber-700"
                        role="alert"
                    >
                        Warning: Caps Lock is on.
                    </p>
                </div>

                <label class="flex items-center gap-2">

                    <input
                        id="remember_me"
                        type="checkbox"
                        name="remember"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    >

                    <span class="text-sm text-slate-600">
                        Remember me
                    </span>

                </label>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-md transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200"
                >
                    Login
                </button>

            </form>

        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-8 py-5 text-center">

            <p class="text-xs text-slate-500">
                © {{ now()->year }} MCST Information Systems Department
            </p>

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
                toggleButton.textContent = isHidden ? 'Hide' : 'Show';
            });

            passwordInput.addEventListener('keyup', function (event) {
                const capsLockIsOn = event.getModifierState
                    && event.getModifierState('CapsLock');

                capsLockWarning.classList.toggle('hidden', ! capsLockIsOn);
            });

            passwordInput.addEventListener('blur', function () {
                capsLockWarning.classList.add('hidden');
            });
        });
    </script>

</x-guest-layout>
