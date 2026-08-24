<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'MCST Gym Reservation')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100">

<div class="flex min-h-screen">

    @include('components.sidebar')

    <div class="flex-1 flex flex-col">

        @include('components.navbar')

        <main class="p-8">

            @yield('content')

        </main>

    </div>

</div>

</body>
</html>
