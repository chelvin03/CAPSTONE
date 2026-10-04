<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Reserve the MCST Gymnasium, review schedules, and track your reservation request.">
    <title>@yield('title', 'MCST Gymnasium | Public Reservation Portal')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body class="landing">
<a class="skip-link" href="#main-content">Skip to main content</a>
@include('public.partials.header')
<main id="main-content">@yield('content')</main>
<footer class="landing-footer"><div class="landing-container footer-grid"><div><a class="brand" href="{{ route('home') }}"><img src="{{ asset('images/mcst-logo.png') }}" alt="" width="44" height="44"><span>MCST Gymnasium<small>Mandaluyong College of Science and Technology</small></span></a><p>Gymnasium Reservation and Utilization System<br>with Business Intelligence</p></div></div><div class="landing-container copyright">&copy; {{ now()->year }} MCST Gymnasium Reservation and Utilization System. Academic capstone project.</div></footer>
</body>
</html>
