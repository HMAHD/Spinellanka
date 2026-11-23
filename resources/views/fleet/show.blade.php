@extends('layouts.app')

@section('title', $vehicle->public_name.' — Specifications — Spinellanka')
@section('meta_description', 'Specifications and details for '.$vehicle->public_name.' ('.$vehicle->make.' '.$vehicle->model.'). Select this exact vehicle for transport in Sri Lanka.')

@section('content')
<main id="main-content" class="content-page">
  <div class="content-stage">
    <header class="content-hero" aria-labelledby="page-title">
      <a class="back-link" href="{{ route('fleet.index') }}">All vehicles</a>
      <h1 id="page-title" tabindex="-1">{{ $vehicle->public_name }}</h1>
      <p class="content-lead">{{ $vehicle->make }} {{ $vehicle->model }} &bull; {{ $vehicle->year }} &bull; {{ $vehicle->vehicle_type->getLabel() }}</p>
    </header>

    <div class="vehicle-detail-layout">
      <div class="vehicle-media-area">
        @if($vehicle->publicApprovedMedia->isNotEmpty())
          <div class="vehicle-gallery" aria-label="Photos of {{ $vehicle->public_name }}">
            @foreach($vehicle->publicApprovedMedia as $media)
              <figure class="vehicle-gallery-item">
                <img
                  src="{{ $media->getUrl() }}"
                  alt="{{ $media->alt_text }}"
                  loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                  width="800"
                  height="530"
                >
                @if($media->alt_text)
                  <figcaption class="sr-only">{{ $media->alt_text }}</figcaption>
                @endif
              </figure>
            @endforeach
          </div>
        @else
          <div class="fleet-gallery-empty" role="status">
            <p>Approved photography for {{ $vehicle->public_name }} is currently being prepared with the operator.</p>
          </div>
        @endif
      </div>

      <div class="vehicle-info-area">
        <section class="vehicle-specs-card" aria-labelledby="specs-title">
          <h2 id="specs-title">Verified Specifications</h2>

          <dl class="vehicle-specs-list">
            <div>
              <dt>Make &amp; model</dt>
              <dd>{{ $vehicle->make }} {{ $vehicle->model }}</dd>
            </div>
            <div>
              <dt>Year</dt>
              <dd>{{ $vehicle->year }}</dd>
            </div>
            <div>
              <dt>Vehicle type</dt>
              <dd>{{ $vehicle->vehicle_type->getLabel() }}</dd>
            </div>
            <div>
              <dt>Passenger capacity</dt>
              <dd>Up to {{ $vehicle->passenger_capacity }} passengers</dd>
            </div>
            <div>
              <dt>Luggage capacity</dt>
              <dd>{{ $vehicle->luggage_capacity }} standard bags</dd>
            </div>
            <div>
              <dt>Transmission</dt>
              <dd>{{ $vehicle->transmission->getLabel() }}</dd>
            </div>
            <div>
              <dt>Air conditioning</dt>
              <dd>{{ $vehicle->has_ac ? 'Equipped with climate control' : 'No air conditioning' }}</dd>
            </div>
            @if(!empty($vehicle->accessibility_features))
              <div>
                <dt>Accessibility</dt>
                <dd>{{ implode(', ', $vehicle->accessibility_features) }}</dd>
              </div>
            @endif
            @if(!empty($vehicle->amenities))
              <div>
                <dt>Features</dt>
                <dd>{{ implode(', ', $vehicle->amenities) }}</dd>
              </div>
            @endif
          </dl>
        </section>

        <section class="vehicle-services-card" aria-labelledby="eligibility-title">
          <h2 id="eligibility-title">Service Eligibility</h2>
          <p class="section-hint">This vehicle is approved for the following advance-booked services:</p>
          <ul class="service-tags" aria-label="Eligible services">
            @foreach($vehicle->eligibleServiceLabels() as $serviceLabel)
              <li>{{ $serviceLabel }}</li>
            @endforeach
          </ul>
        </section>

        <section class="vehicle-booking-notice" aria-labelledby="booking-notice-title">
          <h2 id="booking-notice-title">Advance Inquiry</h2>
          <p>Instant online booking will open sequentially across our four services. Advance inquiries for upcoming travel can be submitted directly to our operations desk.</p>
          <a class="primary-action" href="{{ route('contact') }}">Inquire about this vehicle</a>
        </section>
      </div>
    </div>

    @include('partials.footer')
  </div>
</main>
@endsection

