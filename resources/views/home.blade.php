@extends('layouts.app')

@section('title', 'Spinellanka — Transport planned before you arrive')
@section('meta_description', 'Choose Airport Pickup, Airport Transfer, Ride, or Rent for an advance transport request in Sri Lanka.')

@section('content')
<main id="main-content">
  <section class="home-hero" aria-labelledby="page-title">
    <div class="home-hero-copy">
      <h1 id="page-title" tabindex="-1">Transport planned ahead</h1>
      <p>Advance-book one of four transport services in Sri Lanka, then select the exact vehicle that fits your request.</p>
      <div class="home-hero-actions">
        <a class="primary-action" href="#service-desk">Choose your service</a>
        <a class="text-action" href="{{ route('how-it-works') }}">See how requests work</a>
      </div>
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
  </section>

  <section class="service-catalog" id="service-desk" aria-labelledby="services-title">
    <header class="section-intro">
      <h2 id="services-title">Choose your service</h2>
      <p>Start with the travel need that matches your plans. Each service has its own request details and vehicle requirements.</p>
    </header>

    <ul class="service-choice-list" role="list">
      @foreach($services as $service)
        <li>
          <a href="{{ route('services.show', ['service' => $service->value]) }}">
            <span class="service-choice-name">{{ $service->label() }}</span>
            <span class="service-choice-summary">{{ $service->summary() }}</span>
            <span class="service-choice-action" aria-hidden="true">View service</span>
          </a>
        </li>
      @endforeach
    </ul>
  </section>

  <section class="vehicle-principle" aria-labelledby="vehicle-title">
    <div class="vehicle-principle-statement" aria-hidden="true">
      <span>One request.</span>
      <span>One exact vehicle.</span>
    </div>
    <div class="vehicle-principle-copy">
      <h2 id="vehicle-title">Know what arrives</h2>
      <p>Browse verified fleet records and choose the exact available vehicle that suits your passengers, luggage, and service.</p>
      <a class="text-action" href="{{ route('fleet.index') }}">Explore the fleet</a>
    </div>
  </section>

  <section class="request-process" id="process" aria-labelledby="process-title">
    <header class="section-intro section-intro-on-dark">
      <h2 id="process-title">A clear request process</h2>
      <p>Pricing, availability, and booking actions arrive only with verified fleet and provider data.</p>
    </header>

    <ol class="process-steps">
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

  <section class="home-closing" aria-labelledby="closing-title">
    <div>
      <h2 id="closing-title">Start with the journey</h2>
      <p>No price or vehicle detail appears until verified data is available.</p>
    </div>
    <a class="primary-action" href="#service-desk">Choose your service</a>
  </section>

  @include('partials.footer')
</main>
@endsection
