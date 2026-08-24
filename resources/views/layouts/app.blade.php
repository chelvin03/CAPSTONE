<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'MCST Gym Reservation System')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900" x-data="{ navigationOpen: false }">
    <a href="#main-content" class="sr-only z-[60] rounded bg-white p-3 text-blue-700 focus:not-sr-only focus:fixed focus:left-3 focus:top-3">Skip to main content</a>

    <div class="flex min-h-screen">

        @include('components.sidebar')

        <div class="min-w-0 flex-1">

            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur lg:px-8">
                <button type="button" @click="navigationOpen = true" class="btn-secondary !min-h-10 !px-3 lg:hidden" aria-label="Open navigation" aria-controls="mobile-navigation"><i class="bi bi-list text-xl" aria-hidden="true"></i></button>
                <div class="ml-auto flex items-center gap-3">
                    <div class="hidden text-right sm:block"><p class="text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p><p class="text-xs capitalize text-slate-500">{{ auth()->user()->role }}</p></div>
                    <a href="{{ route('profile.edit') }}" class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 font-bold text-blue-700" aria-label="Open profile">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</a>
                </div>
            </header>

            <main id="main-content" class="p-4 sm:p-6 lg:p-8">
                @if (session('success'))
                    <div class="alert-success" role="status"><i class="bi bi-check-circle-fill mt-0.5" aria-hidden="true"></i><span>{{ session('success') }}</span></div>
                @endif
                @if (session('error'))
                    <div class="alert-error" role="alert"><i class="bi bi-exclamation-circle-fill mt-0.5" aria-hidden="true"></i><span>{{ session('error') }}</span></div>
                @endif
                @yield('content')
            </main>

        </div>

    </div>

</body>
</html>
