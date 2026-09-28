<x-guest-layout>
    <div class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
        <div class="px-8 pb-8 pt-10">
            <div class="text-center">
                <img src="{{ asset('images/mcst-logo.png') }}" alt="MCST Logo" class="mx-auto h-20 w-20 rounded-full object-contain">
                <h1 class="mt-5 text-2xl font-bold text-slate-900">Check your email</h1>
                <p class="mt-2 text-sm text-slate-500">Enter the six-digit code sent to your account email to finish signing in.</p>
            </div>

            <x-auth-session-status class="mt-6" :status="session('status')" />

            @if ($errors->any())
                <div id="code-errors" class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.code.verify') }}" class="mt-7 space-y-5">
                @csrf
                <div>
                    <label for="code" class="mb-2 block text-sm font-semibold text-slate-700">Verification code</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required autofocus aria-describedby="code-help{{ $errors->any() ? ' code-errors' : '' }}" @if($errors->has('code')) aria-invalid="true" @endif class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-center text-2xl tracking-widest text-slate-900 focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                    <p id="code-help" class="mt-2 text-xs text-slate-500">Codes expire after 10 minutes. Five incorrect attempts temporarily lock verification.</p>
                </div>
                <button type="submit" class="w-full rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-md hover:bg-blue-700 focus:ring-4 focus:ring-blue-200">Verify and sign in</button>
            </form>

            <form method="POST" action="{{ route('login.code.resend') }}" class="mt-5 text-center">
                @csrf
                <button type="submit" class="text-sm font-semibold text-blue-600 hover:underline">Resend code</button>
                <p class="mt-2 text-xs text-slate-500">Wait 60 seconds between emails. Up to five emails every 10 minutes. Check your spam folder too.</p>
            </form>
            <form method="POST" action="{{ route('login.code.cancel') }}" class="mt-5 text-center">
                @csrf
                <button type="submit" class="text-sm text-slate-600 hover:underline">Cancel and return to login</button>
            </form>
        </div>
    </div>
</x-guest-layout>
