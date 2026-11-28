@extends('layouts.app')

@section('title', 'Help & FAQ — Spinellanka')
@section('meta_description', 'Essential information about advance booking, vehicle selection, and travel coordination in Sri Lanka.')

@section('content')
<main id="main-content" class="content-page">
  <div class="content-stage">
    <header class="content-hero" aria-labelledby="page-title">
      <a class="back-link" href="{{ route('home') }}">Home</a>
      <h1 id="page-title" tabindex="-1">Help &amp; Frequently Asked Questions</h1>
      <p class="content-lead">Essential information on how our advance transport booking, vehicle selection, and operations work across Sri Lanka.</p>
    </header>

    <div class="pending-notice" role="status" style="margin-block-start: 2rem;">
      <h2>FAQ Verification in Progress</h2>
      <p>Detailed FAQ items, cancellation conditions, and luggage policies are currently being finalized with the local operator. Verified policy questions will be published here upon completion.</p>
    </div>

    <section class="accordion faq-section" aria-labelledby="faq-heading">
      <h2 id="faq-heading" class="sr-only">Core Operational Information</h2>

      <details class="faq-item" open>
        <summary class="faq-question">How far in advance should I book?</summary>
        <div class="faq-answer">
          <p>Spinellanka is strictly an advance-booking service, not an on-demand taxi or live ride-hailing app. We advise requesting as early as possible so staff can confirm vehicle availability, inspect vehicle readiness, and verify turnaround buffers.</p>
        </div>
      </details>

      <details class="faq-item">
        <summary class="faq-question">What does exact vehicle selection mean?</summary>
        <div class="faq-answer">
          <p>Traditional car hire and transfer services often assign a broad vehicle class upon arrival. Spinellanka allows you to select the exact vehicle—complete with verified passenger seating, luggage limits, and specifications—ensuring no unexpected substitutions.</p>
        </div>
      </details>

      <details class="faq-item">
        <summary class="faq-question">What time zone are bookings scheduled in?</summary>
        <div class="faq-answer">
          <p>All flight arrivals, pickup times, and driver schedules are set in Sri Lanka Standard Time (UTC+5:30). If booking from abroad, please confirm your requested times in local Sri Lanka time.</p>
        </div>
      </details>

      <details class="faq-item">
        <summary class="faq-question">What four services are available?</summary>
        <div class="faq-answer">
          <p>We provide Airport Pickup (arrivals with meet-and-greet), Airport Transfer (departures with guided timing), Ride (point-to-point and multi-stop chauffeured journeys), and Rent (exact self-drive hire without driver).</p>
        </div>
      </details>
    </section>

    <section class="closing-section" aria-labelledby="help-contact-title">
      <h2 id="help-contact-title">Have further questions?</h2>
      <p>Our operations desk is available to assist with advance travel planning and custom itineraries.</p>
      <a class="text-action" href="{{ route('contact') }}">Contact operations desk</a>
    </section>
  </div>
  @include('partials.footer')
</main>
@endsection
