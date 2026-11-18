@extends('layouts.app')

@section('title', $service->label().' — Spinellanka')
@section('meta_description', $service->summary())

@section('content')
<main id="main-content" class="service-page">
  <section class="service-hero" aria-labelledby="page-title">
    <div class="service-hero-copy">
      <a class="back-link" href="{{ route('home') }}#services">All services</a>
      <h1 id="page-title" tabindex="-1">{{ $service->label() }}</h1>
      <p>{{ $service->summary() }}</p>
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
      <a class="primary-action" href="{{ route('home') }}#service-desk">Choose another service</a>
    </div>
  </section>

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

  <footer class="service-footer">
    <a href="{{ route('home') }}">Spinellanka home</a>
    <p>&copy; {{ now()->year }} Spinellanka</p>
  </footer>
</main>
@endsection
