@extends('layouts.app')

@section('title', 'Spinellanka — Transport planned before you arrive')
@section('meta_description', 'Choose Airport Pickup, Airport Transfer, Ride, or Rent for an advance transport request in Sri Lanka.')

@section('content')
<main id="main-content">
  <div class="home-stage">
    <div class="reading-panel">
      <div class="intro">
        <section class="intro-copy" aria-labelledby="page-title">
          <h1 id="page-title" tabindex="-1">Transport planned ahead</h1>
          <p class="intro-lead">Advance-book one of four transport services in Sri Lanka, then select the exact vehicle that fits your request.</p>
        </section>

        <dl class="intro-facts" aria-label="Request conditions">
          <div>
            <dt>Vehicle</dt>
            <dd>You choose an exact vehicle, not a vehicle class.</dd>
          </div>
          <div>
            <dt>Confirmation</dt>
            <dd>Every request remains pending until staff confirms it.</dd>
          </div>
        </dl>
      </div>

      <section class="services-section" id="services" aria-labelledby="services-title">
        <h2 id="services-title">Four ways to travel</h2>
        <div class="service-index">
          @foreach($services as $service)
            <article class="service-row" data-reveal>
              <div>
                <h3>{{ $service->label() }}</h3>
                <p>{{ $service->summary() }}</p>
              </div>
              <a href="{{ route('services.show', ['service' => $service->value]) }}" aria-label="View {{ $service->label() }} details">
                View service
              </a>
            </article>
          @endforeach
        </div>
      </section>

      <section class="process-section" id="process" aria-labelledby="process-title">
        <div class="process-copy" data-reveal="horizontal">
          <h2 id="process-title">Request process</h2>
          <p>The public shell shows the approved request sequence. Pricing, availability, and booking actions arrive only with verified fleet and provider data.</p>
        </div>

        <div class="itinerary" aria-label="Advance request sequence" data-reveal>
          <ol>
            <li>
              <strong>Choose a service</strong>
              <span>Start with the travel need that matches your plan.</span>
            </li>
            <li>
              <strong>Select an exact vehicle</strong>
              <span>Review the vehicle and estimate before submitting.</span>
            </li>
            <li>
              <strong>Submit the request</strong>
              <span>The request stays pending while staff checks it.</span>
            </li>
          </ol>
          <p>Staff confirmation completes the booking.</p>
        </div>
      </section>

      <section class="closing-section" aria-labelledby="closing-title" data-reveal="settle">
        <h2 id="closing-title">Start with the service</h2>
        <p>Use the desk to compare the four approved service types. No price or vehicle detail appears until verified data is available.</p>
        <a class="text-action" href="#service-desk">Choose your service</a>
      </section>

      <footer class="site-footer">
        <p>Advance transport requests in Sri Lanka.</p>
        <a href="{{ route('home') }}#services">Services</a>
        <p>&copy; {{ now()->year }} Spinellanka</p>
      </footer>
    </div>

    <aside class="desk-panel" id="service-desk" aria-labelledby="desk-title">
      <div class="desk-inner">
        <div class="desk-wordmark" aria-hidden="true">Spinellanka</div>
        <div class="desk-copy">
          <h2 id="desk-title">Choose your service</h2>
          <p id="service-summary">{{ $services[0]->summary() }}</p>
        </div>

        <form class="service-form" action="{{ route('services.choose') }}" method="get" data-service-form>
          <label for="service-select">Service</label>
          <select id="service-select" name="service" data-service-select>
            @foreach($services as $service)
              <option value="{{ $service->value }}" data-summary="{{ $service->summary() }}">
                {{ $service->label() }}
              </option>
            @endforeach
          </select>
          <button type="submit">View service</button>
        </form>

        <ul class="desk-service-list" aria-label="All services">
          @foreach($services as $service)
            <li><a href="{{ route('services.show', ['service' => $service->value]) }}">{{ $service->label() }}</a></li>
          @endforeach
        </ul>
      </div>
    </aside>
  </div>
</main>
@endsection
