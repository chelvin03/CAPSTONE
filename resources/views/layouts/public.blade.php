<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>@yield('title', 'MCST Gymnasium Reservation System')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100">
    <a href="#main-content" class="sr-only z-50 rounded bg-white p-3 text-blue-700 focus:not-sr-only focus:fixed focus:left-3 focus:top-3">Skip to main content</a>

    <header class="text-white shadow" style="background-color: #136be7;">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">

            <div>
                <h1 class="text-xl font-bold">
                    MCST Gymnasium Reservation System
                </h1>

                <p class="text-sm text-blue-200">
                    Public Reservation Portal
                </p>
            </div>

            <nav class="flex w-full items-center gap-3 sm:w-auto" aria-label="Public navigation">
    <a
        href="{{ route('home') }}"
        class="rounded-lg px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"
    >
        Staff Login
    </a>

    <a
        href="{{ route('reservation.create') }}"
        style="
            display: inline-block;
            padding: 10px 18px;
            background-color: #ffffff;
            color: #2852c4;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        "
    >
        Reserve
    </a>
</nav>

        </div>
    </header>

    <main id="main-content" class="mx-auto max-w-7xl px-4 py-8">
        @yield('content')
    </main>

</body>
</html>
