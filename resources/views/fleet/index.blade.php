@extends('layouts.app')

@section('title', 'Our Fleet — Spinellanka')
@section('meta_description', 'Explore our verified fleet of vehicles in Sri Lanka. Select an exact vehicle for Airport Pickup, Airport Transfer, Ride, or Rent.')

@section('content')
<main id="main-content" class="content-page">
  <div class="content-stage">
    <header class="content-hero" aria-labelledby="page-title">
      <a class="back-link" href="{{ route('home') }}">Home</a>
      <h1 id="page-title" tabindex="-1">Our Fleet</h1>
      <p class="content-lead">Choose from our verified five-vehicle fleet in Sri Lanka. Every booking is for the exact vehicle you select, not a generalized category.</p>

      <dl class="intro-facts" aria-label="Fleet guarantees">
        <div>
          <dt>Exact Vehicle</dt>
          <dd>You select and ride in the exact vehicle chosen.</dd>
        </div>
        <div>
          <dt>Verified Records</dt>
          <dd>All specifications reflect confirmed vehicle records.</dd>
        </div>
      </dl>
    </header>

    <section class="fleet-section" aria-labelledby="fleet-list-title">
      <h2 id="fleet-list-title" class="sr-only">Available vehicles</h2>

      @if($vehicles->isNotEmpty())
        <div class="fleet-grid">
          @foreach($vehicles as $vehicle)
            <article class="fleet-card" data-reveal>
              @if($vehicle->publicApprovedMedia->isNotEmpty())
                <img
                  src="{{ $vehicle->publicApprovedMedia->first()->getUrl() }}"
                  alt="{{ $vehicle->publicApprovedMedia->first()->alt_text }}"
                  class="fleet-card-media"
                  loading="lazy"
                  width="640"
                  height="420"
                >
              @else
                <div class="fleet-card-media fleet-media-empty">
                  <p>Approved photography pending</p>
                </div>
              @endif

              <h3><a class="fleet-title-link" href="{{ route('fleet.show', $vehicle) }}">{{ $vehicle->public_name }}</a></h3>

              <div class="fleet-card-body">
                <p class="fleet-card-subtitle">{{ $vehicle->make }} {{ $vehicle->model }} ({{ $vehicle->year }}) &bull; {{ $vehicle->vehicle_type->getLabel() }}</p>

                <dl class="fleet-card-specs">
                  <div>
                    <dt>Passengers</dt>
                    <dd>Up to {{ $vehicle->passenger_capacity }}</dd>
                  </div>
                  <div>
                    <dt>Luggage</dt>
                    <dd>{{ $vehicle->luggage_capacity }} bags</dd>
                  </div>
                  <div>
                    <dt>Transmission</dt>
                    <dd>{{ $vehicle->transmission->getLabel() }}</dd>
                  </div>
                  <div>
                    <dt>Air conditioning</dt>
                    <dd>{{ $vehicle->has_ac ? 'Included' : 'None' }}</dd>
                  </div>
                </dl>

                <div class="service-tags" role="list" aria-label="Eligible services for {{ $vehicle->public_name }}">
                  @foreach($vehicle->eligibleServiceLabels() as $serviceLabel)
                    <span role="listitem">{{ $serviceLabel }}</span>
                  @endforeach
                </div>
              </div>

              <footer class="fleet-card-footer">
                <a class="text-action" href="{{ route('fleet.show', $vehicle) }}">View specifications</a>
                <a class="nav-action" href="{{ route('contact') }}">Inquire</a>
              </footer>
            </article>
          @endforeach
        </div>
      @else
        <div class="fleet-empty" role="status">
          <h2>Fleet verification in progress</h2>
          <p>Vehicle records are being verified with the operator. Exact selectable fleet details will appear here once verified.</p>
          <a class="primary-action" href="{{ route('home') }}#service-desk">View available services</a>
        </div>
      @endif
    </section>
  </div>
  @include('partials.footer')
</main>
@endsection

