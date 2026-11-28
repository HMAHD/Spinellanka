@extends('layouts.app')

@section('title', 'How It Works — Spinellanka')
@section('meta_description', 'Learn how advance transport booking works with Spinellanka in Sri Lanka. Select an exact vehicle and receive staff confirmation.')

@section('content')
<main id="main-content" class="content-page">
  <div class="content-stage">
    <header class="content-hero hiw-hero" aria-labelledby="page-title">
      <div class="hiw-hero-copy" data-reveal>
        <a class="back-link" href="{{ route('home') }}">Home</a>
        <h1 id="page-title" tabindex="-1">How It Works</h1>
        <p class="content-lead">Spinellanka coordinates planned transport across Sri Lanka. Every journey is arranged ahead of travel with an exact vehicle and reviewed directly by our operations desk.</p>

        <dl class="intro-facts" aria-label="Core operational rules">
          <div>
            <dt>Advance Booking</dt>
            <dd>Planned travel arranged prior to arrival; no on-demand dispatch or guesswork.</dd>
          </div>
          <div>
            <dt>Exact Vehicle</dt>
            <dd>You select the specific vehicle from our fleet, not an anonymous vehicle class.</dd>
          </div>
        </dl>
      </div>
    </header>

    <section class="hiw-steps-section" aria-labelledby="steps-title">
      <header class="section-intro" data-reveal>
        <h2 id="steps-title">Four steps from plan to confirmation</h2>
        <p>Every request passes availability checks, vehicle suitability rules, and human operations verification before confirmation.</p>
      </header>

      <ol class="hiw-steps-grid">
        <li class="hiw-step-card" data-reveal>
          <div class="hiw-step-head">
            <span class="hiw-step-num">01</span>
            <span class="hiw-step-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 7v5l3 2"/>
              </svg>
            </span>
          </div>
          <h3>1. Choose a service</h3>
          <p>Start with Airport Pickup, Airport Transfer, Ride, or Rent—or pick a preconfigured corridor under Curated Deals to pre-fill your route.</p>
        </li>

        <li class="hiw-step-card" data-reveal>
          <div class="hiw-step-head">
            <span class="hiw-step-num">02</span>
            <span class="hiw-step-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="7" width="18" height="10" rx="2"/>
                <circle cx="7.5" cy="17" r="1.5"/>
                <circle cx="16.5" cy="17" r="1.5"/>
              </svg>
            </span>
          </div>
          <h3>2. Select your exact vehicle</h3>
          <p>Review vehicles filtered strictly by your schedule, passenger seats, luggage bags, and turnaround buffers—then place a 15-minute checkout hold.</p>
        </li>

        <li class="hiw-step-card" data-reveal>
          <div class="hiw-step-head">
            <span class="hiw-step-num">03</span>
            <span class="hiw-step-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
              </svg>
            </span>
          </div>
          <h3>3. Submit an advance request</h3>
          <p>Review base rates, applicable promotions, LKR approximation, and traveler contacts. Submitted requests start in clear pending status.</p>
        </li>

        <li class="hiw-step-card" data-reveal>
          <div class="hiw-step-head">
            <span class="hiw-step-num">04</span>
            <span class="hiw-step-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l7 4v6c0 5-3 8.5-7 10-4-1.5-7-5-7-10V6l7-4z"/>
                <path d="M9.5 12l2 2 3.5-3.5"/>
              </svg>
            </span>
          </div>
          <h3>4. Staff confirmation &amp; proposal</h3>
          <p>Our operations team re-checks flight numbers, highway buffers, and assigned driver schedules before confirming your journey.</p>
        </li>
      </ol>
    </section>

    <section class="hiw-matrix-section" aria-labelledby="matrix-title">
      <header class="section-intro" data-reveal>
        <h2 id="matrix-title">Four services built for distinct journeys</h2>
        <p>Compare travel mode, timing rules, and direct booking entry points across every Spinellanka service.</p>
      </header>

      <div class="hiw-matrix-grid">
        <article class="hiw-service-card" data-reveal>
          <div class="hiw-service-top">
            <span class="service-tag">With Driver</span>
            <h3>Airport Pickup</h3>
          </div>
          <p>Bandaranaike International Airport (CMB) arrivals with flight-number tracking, terminal name-board meet-and-greet, and direct hotel drop-off.</p>
          <dl class="hiw-service-meta">
            <div>
              <dt>Waiting Window</dt>
              <dd>90 min complimentary</dd>
            </div>
            <div>
              <dt>Timing Authority</dt>
              <dd>Asia/Colombo (UTC+5:30)</dd>
            </div>
          </dl>
          <div class="hiw-service-actions">
            <a href="{{ route('booking.airport-pickup') }}" class="primary-action">Book Pickup &rsaquo;</a>
            <a href="{{ route('deals.index', ['service' => 'airport_pickup']) }}" class="text-action">Pickup Deals</a>
          </div>
        </article>

        <article class="hiw-service-card" data-reveal>
          <div class="hiw-service-top">
            <span class="service-tag">With Driver</span>
            <h3>Airport Transfer</h3>
          </div>
          <p>Departure journeys back to CMB timed around a 180-minute airport check-in buffer, calculated road drive time, and 45-minute contingency.</p>
          <dl class="hiw-service-meta">
            <div>
              <dt>Safety Buffer</dt>
              <dd>Guided pickup calculation</dd>
            </div>
            <div>
              <dt>Expressway</dt>
              <dd>Highway tolls included</dd>
            </div>
          </dl>
          <div class="hiw-service-actions">
            <a href="{{ route('booking.airport-transfer') }}" class="primary-action">Book Transfer &rsaquo;</a>
            <a href="{{ route('deals.index', ['service' => 'airport_transfer']) }}" class="text-action">Transfer Deals</a>
          </div>
        </article>

        <article class="hiw-service-card" data-reveal>
          <div class="hiw-service-top">
            <span class="service-tag">With Driver</span>
            <h3>Chauffeured Ride</h3>
          </div>
          <p>Point-to-point intercity charters and multi-stop sightseeing routes across Sri Lanka with a permanent driver and optional return leg.</p>
          <dl class="hiw-service-meta">
            <div>
              <dt>Waypoints</dt>
              <dd>Up to 10 en-route stops</dd>
            </div>
            <div>
              <dt>Notice</dt>
              <dd>Min 6 hours advance</dd>
            </div>
          </dl>
          <div class="hiw-service-actions">
            <a href="{{ route('booking.ride') }}" class="primary-action">Book Ride &rsaquo;</a>
            <a href="{{ route('deals.index', ['service' => 'ride']) }}" class="text-action">Touring Deals</a>
          </div>
        </article>

        <article class="hiw-service-card" data-reveal>
          <div class="hiw-service-top">
            <span class="service-tag">Self-Drive</span>
            <h3>Self-Drive Rent</h3>
          </div>
          <p>Independent 24-hour rental cycles of exact vehicles collected in Colombo or CMB—strictly no driver assigned, with physical document check.</p>
          <dl class="hiw-service-meta">
            <div>
              <dt>Handover</dt>
              <dd>Original IDP &amp; passport in person</dd>
            </div>
            <div>
              <dt>Allowance</dt>
              <dd>100 km/day cumulative</dd>
            </div>
          </dl>
          <div class="hiw-service-actions">
            <a href="{{ route('booking.rent') }}" class="primary-action">Book Rent &rsaquo;</a>
            <a href="{{ route('deals.index', ['service' => 'rent']) }}" class="text-action">Self-Drive Deals</a>
          </div>
        </article>
      </div>
    </section>

    <section class="standards-section" aria-labelledby="standards-title">
      <h2 id="standards-title" data-reveal>Operating Standards</h2>
      <div class="standards-grid">
        <article class="standard-tile" data-reveal>
          <span class="standard-accent" aria-hidden="true">01</span>
          <h3>Sri Lanka Standard Time</h3>
          <p>All flight monitoring, pickup windows, and itineraries run strictly on Sri Lanka Standard Time (UTC+5:30) so overseas schedules align accurately on arrival.</p>
        </article>
        <article class="standard-tile" data-reveal>
          <span class="standard-accent" aria-hidden="true">02</span>
          <h3>Turnaround &amp; Preparation Buffers</h3>
          <p>We lock strict turnaround cleaning and inspection intervals between trips so your chosen vehicle arrives prepared without rushed handoffs.</p>
        </article>
        <article class="standard-tile" data-reveal>
          <span class="standard-accent" aria-hidden="true">03</span>
          <h3>In-Person Handover for Rent</h3>
          <p>For self-drive Rent, original driving licences, passports, and International Driving Permits are inspected in person at handover—never uploaded online.</p>
        </article>
      </div>

      <div class="cta-banner cta-banner-light" data-reveal>
        <div>
          <h2>Ready to plan your journey?</h2>
          <p>Explore all four transport services, review curated corridor deals, or inspect exact specifications across our fleet.</p>
        </div>
        <div class="cta-banner-actions">
          <a class="primary-action" href="{{ route('home') }}#service-desk">Choose your service &rsaquo;</a>
          <a class="btn-secondary" href="{{ route('deals.index') }}">Browse Curated Deals</a>
        </div>
      </div>
    </section>
  </div>
  @include('partials.footer')
</main>
@endsection
