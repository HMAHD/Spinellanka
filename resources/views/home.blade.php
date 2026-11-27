@extends('layouts.app')

@section('title', 'Spinellanka — Transport planned before you arrive')
@section('meta_description', 'Choose Airport Pickup, Airport Transfer, Ride, or Rent for an advance transport request in Sri Lanka.')

@section('content')
@php
  $heroLeadDeal = isset($featuredDeals) ? $featuredDeals->first(fn ($d) => $d->isMediaApproved() && $d->image_path) : null;
  $heroImage = $heroLeadDeal?->image_path ?? asset('images/hero-transport.webp');
  $heroAlt = $heroLeadDeal?->image_alt ?? 'Spinellanka executive transport along a palm-lined coastal road in Sri Lanka at golden hour';
  $serviceArtwork = [
    'airport_pickup' => asset('images/deal-airport-pickup.webp'),
    'airport_transfer' => asset('images/deal-airport-transfer.webp'),
    'ride' => asset('images/deal-ride.webp'),
    'rent' => asset('images/deal-rent.webp'),
  ];
  $hasAnyDeals = isset($dealsByService) && collect($dealsByService)->flatten(1)->isNotEmpty();
@endphp

<main id="main-content">
  <section class="home-hero" aria-labelledby="page-title">
    <div class="home-hero-copy" data-reveal>
      <h1 id="page-title" tabindex="-1">Transport planned ahead</h1>
      <p>Advance-book one of four transport services in Sri Lanka, then select the exact vehicle that fits your request.</p>
      <div class="home-hero-actions">
        <a class="primary-action" href="#service-desk">Choose your service</a>
        <a class="text-action" href="{{ $hasAnyDeals ? '#service-deals' : route('how-it-works') }}">
          {{ $hasAnyDeals ? 'Explore travel deals' : 'See how requests work' }}
        </a>
      </div>

      <dl class="home-assurances" aria-label="Request conditions">
        <div>
          <dt>Exact vehicle</dt>
          <dd>You choose the specific vehicle, not a vehicle class.</dd>
        </div>
        <div>
          <dt>Staff reviewed</dt>
          <dd>Every request remains pending until staff confirms it.</dd>
        </div>
      </dl>
    </div>

    <figure class="home-hero-visual" data-reveal>
      <img
        src="{{ $heroImage }}"
        alt="{{ $heroAlt }}"
        width="1280"
        height="720"
        fetchpriority="high"
        decoding="async"
      >
      <figcaption class="home-hero-caption">
        @if($heroLeadDeal)
          <a href="{{ route('deals.show', $heroLeadDeal->slug) }}" class="hero-caption-link">
            <span>Featured Route: {{ $heroLeadDeal->title }}</span>
            <span aria-hidden="true">&rsaquo;</span>
          </a>
        @else
          <span>Dedicated Airport, Intercity &amp; Self-Drive Mobility in Sri Lanka</span>
        @endif
      </figcaption>
    </figure>
  </section>

  <section class="service-catalog" id="service-desk" aria-labelledby="services-title">
    <header class="section-intro">
      <h2 id="services-title">Choose your service</h2>
      <p>Start with the travel need that matches your plans. Each service has its own request details, verified deals, and vehicle requirements.</p>
    </header>

    <ul class="service-card-grid" role="list">
      @foreach($services as $service)
        @php
          $matchingDeals = $dealsByService[$service->value] ?? collect();
          $artSrc = $serviceArtwork[$service->value] ?? asset('images/hero-transport.webp');
        @endphp
        <li class="service-feature-card" data-reveal>
          <a href="{{ route('services.show', ['service' => $service->value]) }}" class="service-feature-link">
            <div class="service-feature-media">
              <img
                src="{{ $artSrc }}"
                alt="{{ $service->label() }} fleet service in Sri Lanka"
                width="640"
                height="360"
                loading="lazy"
                decoding="async"
              >
              <span class="service-mode-chip">{{ $service->includesDriver() ? 'With Driver' : 'Self-Drive' }}</span>
            </div>
            <div class="service-feature-body">
              <h3 class="service-choice-name">{{ $service->label() }}</h3>
              <p class="service-choice-summary">{{ $service->summary() }}</p>
              <div class="service-feature-footer">
                <span class="service-choice-action">View service &rsaquo;</span>
                @if($matchingDeals->isNotEmpty())
                  <span class="service-deals-count">{{ $matchingDeals->count() }} {{ \Illuminate\Support\Str::plural('package', $matchingDeals->count()) }}</span>
                @endif
              </div>
            </div>
          </a>
        </li>
      @endforeach
    </ul>
  </section>

  @if($hasAnyDeals)
    <section class="home-service-deals" id="service-deals" aria-labelledby="deals-section-title">
      <header class="section-intro">
        <h2 id="deals-section-title">Curated Deals Across Every Service</h2>
        <p>Preconfigured itineraries across Airport Pickup, Airport Transfer, Ride, and Self-Drive Rent with direct booking handoff.</p>
      </header>

      <div class="service-deals-bands">
        @foreach($services as $service)
          @php
            $serviceDeals = $dealsByService[$service->value] ?? collect();
          @endphp
          @if($serviceDeals->isNotEmpty())
            <div class="service-deals-band" data-reveal>
              <div class="band-header">
                <h3>{{ $service->label() }}</h3>
                <a href="{{ route('deals.index', ['service' => $service->value]) }}" class="text-action">
                  All {{ $service->label() }} Deals &rsaquo;
                </a>
              </div>

              <div class="deals-grid">
                @foreach($serviceDeals as $deal)
                  <article class="deal-card">
                    @php
                      $cardImg = ($deal->isMediaApproved() && $deal->image_path)
                        ? $deal->image_path
                        : ($serviceArtwork[$deal->service_type->value] ?? null);
                    @endphp
                    @if($cardImg)
                      <img
                        src="{{ $cardImg }}"
                        alt="{{ $deal->image_alt ?? $deal->title }}"
                        class="deal-card-media"
                        loading="lazy"
                        decoding="async"
                        width="640"
                        height="400"
                      >
                    @else
                      <div class="deal-card-media deal-media-fallback">
                        <span class="fallback-badge">{{ $deal->service_type->label() }}</span>
                      </div>
                    @endif

                    <header class="deal-card-header">
                      <div class="deal-badges">
                        <span class="service-tag">{{ $deal->service_type->label() }}</span>
                        @if($deal->promotion)
                          <span class="promotion-tag">Promotion Included</span>
                        @endif
                      </div>
                      <h4>
                        <a href="{{ route('deals.show', $deal->slug) }}" class="card-link">
                          {{ $deal->title }}
                        </a>
                      </h4>
                    </header>

                    <div class="deal-card-content">
                      <p>{{ $deal->summary }}</p>
                    </div>

                    <footer class="deal-card-footer">
                      <span class="deal-cta">Explore Deal &rsaquo;</span>
                    </footer>
                  </article>
                @endforeach
              </div>
            </div>
          @endif
        @endforeach
      </div>

      <div class="section-cta-row">
        <a href="{{ route('deals.index') }}" class="primary-action">Browse All Curated Deals &rsaquo;</a>
      </div>
    </section>
  @endif

  <section class="vehicle-principle" aria-labelledby="vehicle-title">
    <div class="vehicle-principle-statement" aria-hidden="true" data-reveal>
      <span>One request.</span>
      <span>One exact vehicle.</span>
    </div>
    <div class="vehicle-principle-copy" data-reveal>
      <h2 id="vehicle-title">Know what arrives</h2>
      <p>Browse verified fleet records and choose the exact available vehicle that suits your passengers, luggage, and service.</p>
      <a class="text-action" href="{{ route('fleet.index') }}">Explore the fleet</a>
    </div>
  </section>

  <section class="request-process" id="process" aria-labelledby="process-title">
    <header class="section-intro section-intro-on-dark" data-reveal>
      <h2 id="process-title">A clear request process</h2>
      <p>Pricing, availability, and booking actions arrive only with verified fleet and provider data.</p>
      <svg class="process-diagram" viewBox="0 0 320 48" fill="none" aria-hidden="true">
        <line x1="20" y1="24" x2="300" y2="24" stroke="#7180BF" stroke-width="2" stroke-dasharray="4 4"/>
        <circle cx="24" cy="24" r="9" fill="#FBBF24"/>
        <circle cx="160" cy="24" r="9" fill="#18235D" stroke="#FBBF24" stroke-width="2.5"/>
        <circle cx="296" cy="24" r="9" fill="#18235D" stroke="#DBE4FF" stroke-width="2.5"/>
      </svg>
    </header>

    <ol class="process-steps" data-reveal>
      <li>
        <strong>Choose a service</strong>
        <span>Start with the travel need that matches your plan.</span>
      </li>
      <li>
        <strong>Select an exact vehicle</strong>
        <span>Review the vehicle and estimate before submitting.</span>
      </li>
      <li>
        <strong>Submit for confirmation</strong>
        <span>Your request stays pending while staff checks every detail.</span>
      </li>
    </ol>
  </section>

  @if(isset($featuredReviews) && $featuredReviews->isNotEmpty())
    <section class="home-verified-reviews" aria-labelledby="reviews-section-title">
      <header class="section-intro-centered" data-reveal>
        <div class="trustpilot-pill">
          <span class="trustpilot-score">★ 4.9 on Trustpilot</span>
          <span aria-hidden="true">&bull;</span>
          <span class="trustpilot-label">Verified Traveler Reviews</span>
        </div>
        <h2 id="reviews-section-title">Traveler Experiences</h2>
        <p>Authentic feedback from international guests who completed verified chauffeured and self-drive bookings with us.</p>
      </header>

      <div class="reviews-grid">
        @foreach($featuredReviews as $review)
          <article class="review-card" data-reveal>
            <div>
              <div class="review-card-top">
                <div class="review-stars" aria-label="{{ $review->rating }} out of 5 stars">
                  @for($i = 0; $i < $review->rating; $i++)&#9733;@endfor
                </div>
                <span class="review-verified-badge">
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M8 1.5L13.5 3.7V7.8C13.5 11.1 11.2 13.7 8 14.5C4.8 13.7 2.5 11.1 2.5 7.8V3.7L8 1.5Z"/>
                    <path d="M5.7 8.1L7.3 9.7L10.5 6.4" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  Verified Journey
                </span>
              </div>

              @if($review->title)
                <h3 class="review-title">&ldquo;{{ $review->title }}&rdquo;</h3>
              @endif

              <p class="review-quote">{{ $review->review_text }}</p>
            </div>

            <footer class="review-card-footer">
              <div>
                <div class="review-author">{{ $review->traveler_name }}</div>
                @if($review->traveler_country)
                  <div class="review-country">{{ $review->traveler_country }}</div>
                @endif
              </div>
              @if($review->service_type)
                <span class="review-service-label">{{ $review->service_type->label() }}</span>
              @endif
            </footer>
          </article>
        @endforeach
      </div>
    </section>
  @endif

  <section class="home-closing" aria-labelledby="closing-title" data-reveal>
    <div>
      <h2 id="closing-title">Start with the journey</h2>
      <p>No price or vehicle detail appears until verified data is available.</p>
    </div>
    <a class="primary-action" href="#service-desk">Choose your service</a>
  </section>

  @include('partials.footer')
</main>
@endsection
