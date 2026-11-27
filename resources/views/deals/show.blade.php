@extends('layouts.app')

@section('title', "{$deal->title} — Spinellanka Travel Package")
@section('meta_description', e($deal->summary))

@section('content')
<main id="main-content" class="content-page deal-detail-page">
  <div class="content-stage">
    <nav class="breadcrumb-nav" aria-label="Breadcrumb">
      <a class="back-link" href="{{ route('home') }}">Home</a> &rsaquo;
      <a href="{{ route('deals.index') }}">Deals</a> &rsaquo;
      <span aria-current="page">{{ $deal->title }}</span>
    </nav>

    <div class="deal-detail-layout">
      <!-- Left Column: Deal Information -->
      <article class="deal-main-article">
        <header class="deal-header">
          <div class="deal-badges">
            <span class="service-tag">{{ $deal->service_type->label() }}</span>
            @if($deal->is_featured)
              <span class="featured-tag">Featured</span>
            @endif
            @if($deal->promotion)
              <span class="promotion-tag">Promotion Included ({{ $deal->promotion->name }})</span>
            @endif
          </div>
          <h1 id="page-title" tabindex="-1">{{ $deal->title }}</h1>
          <p class="deal-summary">{{ $deal->summary }}</p>
        </header>

        @php
          $detailArtwork = [
            'airport_pickup' => asset('images/deal-airport-pickup.webp'),
            'airport_transfer' => asset('images/deal-airport-transfer.webp'),
            'ride' => asset('images/deal-ride.webp'),
            'rent' => asset('images/deal-rent.webp'),
          ];
          $detailImage = ($deal->isMediaApproved() && $deal->image_path)
            ? $deal->image_path
            : (empty($deal->image_path) ? ($detailArtwork[$deal->service_type->value] ?? null) : null);
        @endphp
        @if($detailImage)
          <figure class="deal-featured-media">
            <img src="{{ $detailImage }}"
                 alt="{{ $deal->image_alt ?? $deal->title }}"
                 class="deal-full-image"
                 fetchpriority="high"
                 decoding="async"
                 width="1200"
                 height="675">
            @if($deal->isMediaApproved() && $deal->image_source)
              <figcaption class="media-caption">Photo: {{ $deal->image_source }}</figcaption>
            @endif
          </figure>
        @endif

        <section class="deal-itinerary-section" aria-labelledby="itinerary-heading">
          <h2 id="itinerary-heading">Package Details &amp; Itinerary</h2>
          <div class="deal-description-body">
            {!! \Illuminate\Support\Str::markdown($deal->description_markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
          </div>
        </section>

        @if(!empty($deal->preset_payload))
          <section class="deal-inclusions-section" aria-labelledby="inclusions-heading">
            <h2 id="inclusions-heading">Preconfigured Route Parameters</h2>
            <dl class="deal-specs-grid">
              @if(!empty($deal->preset_payload['destination']) || !empty($deal->preset_payload['destination_text']))
                <div>
                  <dt>Destination</dt>
                  <dd>{{ $deal->preset_payload['destination'] ?? $deal->preset_payload['destination_text'] }}</dd>
                </div>
              @endif
              @if(!empty($deal->preset_payload['origin']) || !empty($deal->preset_payload['origin_text']))
                <div>
                  <dt>Departure Location</dt>
                  <dd>{{ $deal->preset_payload['origin'] ?? $deal->preset_payload['origin_text'] }}</dd>
                </div>
              @endif
              @if(!empty($deal->preset_payload['airport']))
                <div>
                  <dt>Airport Gateway</dt>
                  <dd>{{ $deal->preset_payload['airport'] }} (Bandaranaike International Airport)</dd>
                </div>
              @endif
              @if(!empty($deal->preset_payload['duration_days']))
                <div>
                  <dt>Included Rental Duration</dt>
                  <dd>{{ $deal->preset_payload['duration_days'] }} Days</dd>
                </div>
              @endif
              @if(!empty($deal->preset_payload['passenger_count']))
                <div>
                  <dt>Recommended Capacity</dt>
                  <dd>Up to {{ $deal->preset_payload['passenger_count'] }} Passengers</dd>
                </div>
              @endif
            </dl>
          </section>
        @endif

        <section class="deal-commitments-section" aria-labelledby="commitments-heading">
          <h2 id="commitments-heading">Our Service Standards</h2>
          <ul class="commitments-list">
            <li><strong>Exact Vehicle Guarantee:</strong> You choose and ride in the exact vehicle confirmed, not an arbitrary vehicle category.</li>
            <li><strong>All-Inclusive Transparent Pricing:</strong> Standard highway tolls and driver allocations are clearly itemized with zero hidden fees.</li>
            <li><strong>15-Minute Checkout Hold:</strong> Your selected vehicle is reserved exclusively for you during checkout.</li>
            <li><strong>Flight Delay Coverage:</strong> Arrival chauffeurs track flight numbers in real-time with complimentary waiting time.</li>
          </ul>
        </section>
      </article>

      <!-- Right Column: Booking Configuration Card -->
      <aside class="deal-booking-sidebar">
        <div class="booking-preset-card">
          <h2 class="sidebar-title">Book This Package</h2>
          <p class="sidebar-desc">Select your intended travel date to proceed into our service desk with this preset pre-loaded.</p>

          <form action="{{ route('deals.book', $deal->slug) }}" method="POST" class="deal-booking-form" novalidate>
            @csrf

            <div class="form-group">
              <label for="travel_date">Travel Date <span class="required">*</span></label>
              <input type="date" 
                     id="travel_date" 
                     name="travel_date" 
                     value="{{ old('travel_date', now('Asia/Colombo')->addDays(2)->format('Y-m-d')) }}" 
                     min="{{ now('Asia/Colombo')->format('Y-m-d') }}" 
                     required 
                     class="form-input @error('travel_date') is-invalid @enderror">
              @error('travel_date')
                <span class="form-error">{{ $message }}</span>
              @enderror
            </div>

            <div class="form-group">
              <label for="travel_time">Preferred Departure Time</label>
              <input type="time" 
                     id="travel_time" 
                     name="travel_time" 
                     value="{{ old('travel_time', '10:00') }}" 
                     class="form-input @error('travel_time') is-invalid @enderror">
              @error('travel_time')
                <span class="form-error">{{ $message }}</span>
              @enderror
            </div>

            <div class="sidebar-summary-box">
              <div class="summary-line">
                <span>Service Type:</span>
                <strong>{{ $deal->service_type->label() }}</strong>
              </div>
              @if($deal->promotion)
                <div class="summary-line promo-highlight">
                  <span>Linked Promotion:</span>
                  <strong>{{ $deal->promotion->name }}</strong>
                </div>
              @endif
            </div>

            <button type="submit" class="primary-action deal-book-submit">
              Continue to Vehicle Selection &rsaquo;
            </button>

            <p class="sidebar-footnote">
              Non-authoritative handoff. Live availability check, exact fleet vehicle selection, and checkout hold occur in the next step.
            </p>
          </form>
        </div>
      </aside>
    </div>
  </div>
  @include('partials.footer')
</main>
@endsection

