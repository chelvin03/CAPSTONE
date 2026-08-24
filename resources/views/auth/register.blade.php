<x-guest-layout>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">

        <div class="px-7 pb-7 pt-8">

            <div class="mb-7 text-center">

                <img
                    src="{{ asset('images/mcst-logo.png') }}"
                    alt="MCST Logo"
                    class="mx-auto h-20 w-20 object-contain"
                >

                <h1 class="mt-4 text-xl font-bold text-slate-900">
                    Create an Account
                </h1>

                <p class="mt-1 text-xs text-slate-500">
                    MCST Gymnasium Reservation &amp; Analytics System
                </p>

            </div>

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">

                    <div>
                        <label
                            for="first_name"
                            class="mb-2 block text-xs font-semibold text-slate-700"
                        >
                            First Name
                        </label>

                        <input
                            id="first_name"
                            type="text"
                            name="first_name"
                            value="{{ old('first_name') }}"
                            required
                            autofocus
                            class="block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        >

                        @error('first_name')
                            <p class="mt-1.5 text-xs font-medium text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="last_name"
                            class="mb-2 block text-xs font-semibold text-slate-700"
                        >
                            Last Name
                        </label>

                        <input
                            id="last_name"
                            type="text"
                            name="last_name"
                            value="{{ old('last_name') }}"
                            required
                            class="block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        >

                        @error('last_name')
                            <p class="mt-1.5 text-xs font-medium text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                </div>

                <div class="mt-4">

                    <label
                        for="email"
                        class="mb-2 block text-xs font-semibold text-slate-700"
                    >
                        Work Email
                    </label>

                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="username"
                        placeholder="name@mcst.edu.ph"
                        class="block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    >

                    @error('email')
                        <p class="mt-1.5 text-xs font-medium text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                </div>

                <div class="mt-4">

    <label
        for="contact_number"
        class="mb-2 block text-xs font-semibold text-slate-700"
    >
        Contact Number
    </label>

    <input
        id="contact_number"
        type="text"
        name="contact_number"
        value="{{ old('contact_number') }}"
        placeholder="09XXXXXXXXX"
        class="block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
    >

    @error('contact_number')
        <p class="mt-1.5 text-xs font-medium text-red-600">
            {{ $message }}
        </p>
    @enderror

</div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">

                    <div>
                        <label
                            for="password"
                            class="mb-2 block text-xs font-semibold text-slate-700"
                        >
                            Password
                        </label>

                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            class="block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        >

                        @error('password')
                            <p class="mt-1.5 text-xs font-medium text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="password_confirmation"
                            class="mb-2 block text-xs font-semibold text-slate-700"
                        >
                            Confirm Password
                        </label>

                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            class="block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        >
                    </div>

                </div>

                <button
                    type="submit"
                    class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200"
                >
                    Create Account
                </button>

            </form>

            <p class="mt-5 text-center text-xs text-slate-500">
                Already have an account?

                <a
                    href="{{ route('login') }}"
                    class="font-semibold text-blue-600 hover:underline"
                >
                    Log in
                </a>
            </p>

        </div>

    </div>

</x-guest-layout>
