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
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    @stack('styles')
</head>

<body class="min-h-screen bg-slate-100 @yield('body-class')">
    <a href="#main-content" class="sr-only z-50 rounded bg-white p-3 text-blue-700 focus:not-sr-only focus:fixed focus:left-3 focus:top-3">Skip to main content</a>

    @include('public.partials.header')

    <main id="main-content" class="mx-auto max-w-7xl px-4 py-8">
        @yield('content')
    </main>

</body>
</html>
