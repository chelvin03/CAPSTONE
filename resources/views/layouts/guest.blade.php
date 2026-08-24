<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>MCST Gymnasium</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 font-sans text-slate-900">

    <main class="flex min-h-screen items-center justify-center px-4 py-10">

        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>

    </main>

</body>
</html>
