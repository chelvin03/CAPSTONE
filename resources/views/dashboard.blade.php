<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Requestor Dashboard</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50">

    <main class="mx-auto max-w-7xl px-6 py-10">

        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">

            <div class="flex items-center gap-4">

                <img
                    src="{{ asset('images/mcst-logo.png') }}"
                    alt="MCST Logo"
                    class="h-16 w-16 object-contain"
                >

                <div>
                    <p class="text-sm font-semibold text-blue-700">
                        MCST Gymnasium
                    </p>

                    <h1 class="mt-1 text-3xl font-bold text-slate-900">
                        Requestor Dashboard
                    </h1>

                    <p class="mt-2 text-slate-500">
                        Welcome,
                        {{ Auth::user()->first_name }}
                        {{ Auth::user()->last_name }}.
                    </p>
                </div>

            </div>
            <div class="mt-8">

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button
                        type="submit"
                        class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Logout
                    </button>
                </form>

            </div>

        </div>

    </main>

</body>
</html>
