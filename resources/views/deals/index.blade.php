@extends('layouts.app')

@section('title', 'Curated Travel Deals & Packages — Spinellanka')
@section('meta_description', 'Browse preconfigured chauffeured and self-drive travel packages across Sri Lanka. Honest rates, exact vehicle selection, and verified itineraries.')

@section('content')
<main id="main-content" class="content-page">
  <div class="content-stage">
    <header class="content-hero" aria-labelledby="page-title">
      <nav class="breadcrumb-nav" aria-label="Breadcrumb">
        <a class="back-link" href="{{ route('home') }}">Home</a> &rsaquo;
        <span aria-current="page">Deals</span>
      </nav>
      <h1 id="page-title" tabindex="-1">Curated Travel Deals</h1>
      <p class="content-lead">Preconfigured travel packages across Sri Lanka. Select an itinerary, pick your travel date, and continue seamlessly into our booking desk with verified vehicle selection.</p>

      <form method="GET" action="{{ route('deals.index') }}" class="deals-filter-bar" role="search" aria-label="Filter deals">
        <div class="deals-filter-pills" role="tablist" aria-label="Filter by service">
          <a href="{{ route('deals.index', array_filter(['destination' => $destination])) }}" 
             class="filter-pill {{ $selectedService === null ? 'active' : '' }}" 
             role="tab" 
             aria-selected="{{ $selectedService === null ? 'true' : 'false' }}">
            All Packages
          </a>
          @foreach($services as $service)
            <a href="{{ route('deals.index', array_filter(['service' => $service->value, 'destination' => $destination])) }}" 
               class="filter-pill {{ $selectedService === $service ? 'active' : '' }}" 
               role="tab" 
               aria-selected="{{ $selectedService === $service ? 'true' : 'false' }}">
              {{ $service->label() }}
            </a>
          @endforeach
        </div>

        <div class="deals-search-box">
          @if($selectedService)
            <input type="hidden" name="service" value="{{ $selectedService->value }}">
          @endif
          <label for="destination-search" class="sr-only">Search destination</label>
          <input type="search" 
                 id="destination-search" 
                 name="destination" 
                 value="{{ $destination ?? '' }}" 
                 placeholder="Search destination (e.g. Galle, Kandy, Ella)..." 
                 class="search-input"
                 autocomplete="off">
          <button type="submit" class="search-button">Search</button>
          @if($destination)
            <a href="{{ route('deals.index', array_filter(['service' => $selectedService?->value])) }}" class="clear-search-link">Clear</a>
          @endif
        </div>
      </form>
    </header>

    <section class="deals-catalog-section" aria-labelledby="deals-heading">
      <h2 id="deals-heading" class="sr-only">Available Packages</h2>

      @if($deals->isNotEmpty())
        <div class="deals-grid">
          @foreach($deals as $deal)
            <article class="deal-card" data-reveal>
              @php
                $indexArtwork = [
                  'airport_pickup' => asset('images/deal-airport-pickup.webp'),
                  'airport_transfer' => asset('images/deal-airport-transfer.webp'),
                  'ride' => asset('images/deal-ride.webp'),
                  'rent' => asset('images/deal-rent.webp'),
                ];
                $indexImg = ($deal->isMediaApproved() && $deal->image_path)
                  ? $deal->image_path
                  : (empty($deal->image_path) ? ($indexArtwork[$deal->service_type->value] ?? null) : null);
              @endphp
              @if($indexImg)
                <img src="{{ $indexImg }}"
                     alt="{{ $deal->image_alt ?? $deal->title }}"
                     class="deal-card-media"
                     loading="lazy"
                     decoding="async"
                     width="640"
                     height="400">
              @else
                <div class="deal-card-media deal-media-fallback">
                  <span class="fallback-badge">{{ $deal->service_type->label() }}</span>
                </div>
              @endif

              <header class="deal-card-header">
                <div class="deal-badges">
                  <span class="service-tag">{{ $deal->service_type->label() }}</span>
                  @if($deal->is_featured)
                    <span class="featured-tag">Featured</span>
                  @endif
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

                @if(!empty($deal->preset_payload['destination']) || !empty($deal->preset_payload['destination_text']))
                  <div class="deal-highlight-destination">
                    <span class="label">Destination:</span>
                    <span class="val">{{ $deal->preset_payload['destination'] ?? $deal->preset_payload['destination_text'] }}</span>
                  </div>
                @elseif(!empty($deal->preset_payload['origin']) || !empty($deal->preset_payload['origin_text']))
                  <div class="deal-highlight-destination">
                    <span class="label">Origin:</span>
                    <span class="val">{{ $deal->preset_payload['origin'] ?? $deal->preset_payload['origin_text'] }}</span>
                  </div>
                @endif

                @if(!empty($deal->preset_payload))
                  <ul class="deal-meta-chips" aria-label="Included preset specifications">
                    @if(!empty($deal->preset_payload['airport']))
                      <li>{{ $deal->preset_payload['airport'] }} Airport</li>
                    @endif
                    @if(!empty($deal->preset_payload['duration_days']))
                      <li>{{ $deal->preset_payload['duration_days'] }} Days</li>
                    @endif
                    @if(!empty($deal->preset_payload['passenger_count']))
                      <li>Up to {{ $deal->preset_payload['passenger_count'] }} Guests</li>
                    @endif
                  </ul>
                @endif
              </div>

              <footer class="deal-card-footer">
                <span class="deal-cta">View Details &amp; Reserve &rsaquo;</span>
              </footer>
            </article>
          @endforeach
        </div>
      @else
        <div class="deals-empty-state">
          <div class="empty-icon" aria-hidden="true">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
              <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
          </div>
          <h3>No packages found</h3>
          <p>No published travel packages match your current search filters. You can configure a tailored trip directly through our service desk.</p>
          <div class="empty-actions">
            <a href="{{ route('deals.index') }}" class="btn-secondary">View All Packages</a>
            <a href="{{ route('home') }}#service-desk" class="btn-primary">Browse All Services</a>
          </div>
        </div>
      @endif
    </section>
  </div>
  @include('partials.footer')
</main>
@endsection

