@extends('layouts.landing')
@section('content')
<section class="landing-hero" id="home"><div class="landing-container hero-grid"><div><p class="eyebrow hero-eyebrow"><span></span> A SPACE TO CONNECT, PLAY & GATHER</p><h1>Welcome to<br><span>MCST Gymnasium</span></h1><p class="hero-description">Reserve the gymnasium easily, check available schedules, and track your reservation requests in one convenient system.</p><div class="button-row"><a class="landing-button button-white" href="{{ route('reservation.create') }}">Reserve Now <i class="bi bi-arrow-right" aria-hidden="true"></i></a><a class="landing-button button-outline" href="{{ route('public.schedule') }}"><i class="bi bi-calendar3" aria-hidden="true"></i> View Schedule</a></div><p class="hero-footnote"><i class="bi bi-shield-check" aria-hidden="true"></i> Online requests. Verified email. Reviewed bookings.</p></div></div></section>
<section class="landing-section" aria-labelledby="how-heading"><div class="landing-container"><div class="section-heading"><p class="eyebrow">YOUR REQUEST, STEP BY STEP</p><h2 id="how-heading">How to Reserve the Gymnasium</h2><p>A simple guide to getting your reservation request started.</p></div><div class="steps-grid">
@foreach([
['01', 'bi-person-lines-fill', 'Fill Out the Form', 'Start with your name, email, contact number, and requestor type.'],
['02', 'bi-envelope-check', 'Verify Your Email', 'Enter the verification code sent to your email to continue to Event Information.'],
['03', 'bi-file-earmark-check', 'Submit Requirements', 'Complete your event details, select a schedule, and upload the required approval letter or permit.'],
['04', 'bi-search', 'Track Your Request', 'Save your reference number and use it to check your request status while it is reviewed.']
] as [$number, $icon, $title, $description])
<article class="step-card"><div class="step-top"><i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $number }}</span></div><h3>{{ $title }}</h3><p>{{ $description }}</p></article>
@endforeach
</div></div></section>
<section class="landing-section about-section" id="about"><div class="landing-container about-grid"><div><p class="eyebrow">BUILT FOR OUR COMMUNITY</p><h2>About Our Gymnasium</h2><p class="section-copy">The MCST Gymnasium supports school activities, sports events, community programs, and other approved gatherings. Our online reservation system helps organize schedules, manage requests, and improve gymnasium utilization.</p><a class="text-link" href="{{ route('reservation.create') }}">Book the Gymnasium <span aria-hidden="true">→</span></a></div><div class="information-cards" id="facilities" aria-label="Gymnasium activities">
@foreach([['bi-mortarboard', 'School Activities', 'A shared space for approved academic gatherings and school programs.'], ['bi-trophy', 'Sports and Events', 'Bring people together through sports activities and organized events.'], ['bi-people', 'Community Programs', 'Support community gatherings through a coordinated reservation process.']] as [$icon, $title, $description])
<article class="information-card"><i class="bi {{ $icon }}" aria-hidden="true"></i><div><h3>{{ $title }}</h3><p>{{ $description }}</p></div></article>
@endforeach
</div></div></section>
<section class="landing-section" id="schedule"><div class="landing-container"><div class="section-heading heading-row"><div><p class="eyebrow">PLAN YOUR NEXT EVENT</p><h2>Check Gymnasium Schedule</h2><p>Review upcoming occupied schedules and restricted periods.</p></div><a class="landing-button button-light" href="{{ route('public.schedule') }}">View Full Schedule <span aria-hidden="true">→</span></a></div>@include('public.partials.schedule')</div></section>
@endsection
