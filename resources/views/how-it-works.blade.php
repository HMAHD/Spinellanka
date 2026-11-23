@extends('layouts.app')

@section('title', 'How It Works — Spinellanka')
@section('meta_description', 'Learn how advance transport booking works with Spinellanka in Sri Lanka. Select an exact vehicle and receive staff confirmation.')

@section('content')
<main id="main-content" class="content-page">
  <div class="content-stage">
    <header class="content-hero" aria-labelledby="page-title">
      <a class="back-link" href="{{ route('home') }}">Home</a>
      <h1 id="page-title" tabindex="-1">How It Works</h1>
      <p class="content-lead">Spinellanka coordinates planned transport across Sri Lanka. Every journey is arranged before arrival with an exact vehicle and confirmed directly by our operations staff.</p>

      <dl class="intro-facts" aria-label="Core operational rules">
        <div>
          <dt>Advance Booking</dt>
          <dd>Planned travel arranged prior to arrival; no live ride dispatch.</dd>
        </div>
        <div>
          <dt>Exact Vehicle</dt>
          <dd>You select the specific vehicle from our fleet, not an anonymous class.</dd>
        </div>
      </dl>
    </header>

    <section class="process-detail-section" aria-labelledby="steps-title">
      <h2 id="steps-title" class="sr-only">Four-step advance booking process</h2>

      <div class="itinerary" aria-label="Step-by-step booking guide">
        <ol>
          <li>
            <strong>1. Choose a service</strong>
            <span>Select from Airport Pickup, Airport Transfer, Ride, or Rent based on your travel needs. Each service is tailored for distinct itineraries with or without a professional driver.</span>
          </li>
          <li>
            <strong>2. Select your exact vehicle</strong>
            <span>Browse our five-vehicle fleet to review passenger capacity, luggage volume, transmission, and verified amenities. The vehicle you choose is the exact vehicle provided.</span>
          </li>
          <li>
            <strong>3. Submit an advance request</strong>
            <span>Provide your arrival details, route, or rental duration. Every submission remains in pending status while our operations team reviews route feasibility and scheduling buffers.</span>
          </li>
          <li>
            <strong>4. Staff confirmation &amp; proposal</strong>
            <span>Our operations desk verifies availability, checks required preparation buffers, and issues an itemized proposal. The booking is finalized upon customer acceptance and payment confirmation.</span>
          </li>
        </ol>
        <p>Every booking is confirmed by staff before travel commences.</p>
      </div>
    </section>

    <section class="standards-section" aria-labelledby="standards-title">
      <h2 id="standards-title">Operating Standards</h2>
      <div class="standards-grid">
        <article class="standard-tile">
          <h3>Sri Lanka Standard Time</h3>
          <p>All flight monitoring, pickup times, and itineraries operate on Sri Lanka Standard Time (UTC+5:30). When booking from overseas, schedule in local Sri Lanka time.</p>
        </article>
        <article class="standard-tile">
          <h3>Turnaround &amp; Preparation Buffers</h3>
          <p>We maintain strict turnaround buffers between trips to allow thorough vehicle cleaning, maintenance checks, and route repositioning.</p>
        </article>
        <article class="standard-tile">
          <h3>In-Person Handover for Rent</h3>
          <p>For self-drive Rent, original driving permits and identification are inspected directly at vehicle handover. Documents are never uploaded online.</p>
        </article>
      </div>

      <div class="cta-banner">
        <h2>Ready to plan your journey?</h2>
        <p>Explore our four services and review fleet specifications.</p>
        <a class="primary-action" href="{{ route('home') }}#service-desk">Choose your service</a>
      </div>
    </section>

    @include('partials.footer')
  </div>
</main>
@endsection

