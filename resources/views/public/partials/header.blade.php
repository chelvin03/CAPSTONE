<header class="landing landing-header" x-data="{ open: false }" @keydown.escape.window="open = false">
    <div class="landing-container nav-shell">
        <a class="brand" href="{{ route('home') }}"><img src="{{ asset('images/mcst-logo.png') }}" alt="MCST logo" width="48" height="48"><span>MCST Gymnasium<small>Public Reservation Portal</small></span></a>
        <button class="menu-toggle" type="button" @click="open = !open" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="public-navigation" aria-label="Toggle navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
        <nav id="public-navigation" class="landing-nav" :class="{ 'is-open': open }" aria-label="Main navigation" @click="if ($event.target.closest('a')) open = false">
            <a href="{{ route('home') }}">Home</a><a href="{{ route('home') }}#about">About</a><a href="{{ route('public.schedule') }}">Schedule</a><a href="{{ route('reservation.track') }}">Track Reservation</a><a class="landing-button" href="{{ route('reservation.create') }}">Reserve Now <span aria-hidden="true">↗</span></a>
        </nav>
    </div>
</header>
