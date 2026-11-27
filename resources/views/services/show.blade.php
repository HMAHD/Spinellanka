@extends('layouts.app')

@section('title', $service->label().' — Spinellanka')
@section('meta_description', $service->summary())

@section('content')
@php
  $serviceArtwork = [
    'airport_pickup' => asset('images/deal-airport-pickup.webp'),
    'airport_transfer' => asset('images/deal-airport-transfer.webp'),
    'ride' => asset('images/deal-ride.webp'),
    'rent' => asset('images/deal-rent.webp'),
  ];
  $artSrc = $serviceArtwork[$service->value] ?? asset('images/hero-transport.webp');
@endphp

<main id="main-content" class="service-page">
  <section class="service-hero" aria-labelledby="page-title">
    <div class="service-hero-copy">
      <a class="back-link" href="{{ route('home') }}#service-desk">All services</a>
      <h1 id="page-title" tabindex="-1">{{ $service->label() }}</h1>
      <p>{{ $service->summary() }}</p>
      <figure class="service-hero-visual">
        <img
          src="{{ $artSrc }}"
          alt="{{ $service->label() }} transport service in Sri Lanka"
          width="960"
          height="540"
          fetchpriority="high"
          decoding="async"
        >
      </figure>
    </div>

    <div class="service-note" aria-labelledby="request-status-title">
      <h2 id="request-status-title">Before a booking is confirmed</h2>
      <p>You select an exact vehicle and submit a request. The request remains pending until staff confirms availability and the booking details.</p>
      <dl>
        <div>
          <dt>Travel mode</dt>
          <dd>{{ $service->includesDriver() ? 'With a driver' : 'Self-drive' }}</dd>
        </div>
        <div>
          <dt>Request status</dt>
          <dd>Pending until staff confirmation</dd>
        </div>
      </dl>
      @if($service === \App\Domain\Booking\ServiceType::AirportPickup)
        <a class="primary-action" href="{{ route('booking.airport-pickup') }}">Book Airport Pickup &rsaquo;</a>
      @elseif($service === \App\Domain\Booking\ServiceType::AirportTransfer)
        <a class="primary-action" href="{{ route('booking.airport-transfer') }}">Book Airport Transfer &rsaquo;</a>
      @elseif($service === \App\Domain\Booking\ServiceType::Ride)
        <a class="primary-action" href="{{ route('booking.ride') }}">Book Chauffeured Ride &rsaquo;</a>
      @elseif($service === \App\Domain\Booking\ServiceType::Rent)
        <a class="primary-action" href="{{ route('booking.rent') }}">Book Self-Drive Rent &rsaquo;</a>
      @else
        <a class="primary-action" href="{{ route('home') }}#service-desk">Choose another service</a>
      @endif
    </div>
  </section>

  @if(isset($serviceDeals) && $serviceDeals->isNotEmpty())
    <section class="service-deals-section" aria-labelledby="service-deals-title">
      <header class="section-intro">
        <h2 id="service-deals-title">Packages for {{ $service->label() }}</h2>
        <p>Select a preconfigured route or rental package for {{ $service->label() }} to pre-fill your trip details with live vehicle availability.</p>
      </header>

      <div class="deals-grid">
        @foreach($serviceDeals as $deal)
          <article class="deal-card" data-reveal>
            @php
              $cardImg = ($deal->isMediaApproved() && $deal->image_path)
                ? $deal->image_path
                : $artSrc;
            @endphp
            <img
              src="{{ $cardImg }}"
              alt="{{ $deal->image_alt ?? $deal->title }}"
              class="deal-card-media"
              loading="lazy"
              decoding="async"
              width="640"
              height="400"
            >

            <header class="deal-card-header">
              <div class="deal-badges">
                <span class="service-tag">{{ $deal->service_type->label() }}</span>
                @if($deal->promotion)
                  <span class="promotion-tag">Promotion Included</span>
                @endif
              </div>
              <h3>
                <a href="{{ route('deals.show', $deal->slug) }}" class="card-link">
                  {{ $deal->title }}
                </a>
              </h3>
            </header>

            <div class="deal-card-content">
              <p>{{ $deal->summary }}</p>
            </div>

            <footer class="deal-card-footer">
              <span class="deal-cta">Explore Package &rsaquo;</span>
            </footer>
          </article>
        @endforeach
      </div>
    </section>
  @endif

  <section class="service-switcher" aria-labelledby="other-services-title">
    <h2 id="other-services-title">Other services</h2>
    <ul>
      @foreach($services as $otherService)
        @continue($otherService === $service)
        <li>
          <a href="{{ route('services.show', ['service' => $otherService->value]) }}">
            <span>{{ $otherService->label() }}</span>
            <span>{{ $otherService->summary() }}</span>
          </a>
        </li>
      @endforeach
    </ul>
  </section>

  @include('partials.footer')
</main>
@endsection
