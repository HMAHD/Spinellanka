@extends('layouts.app')

@section('title', 'Help & FAQ — Spinellanka')
@section('meta_description', 'Frequently asked questions about advance booking, vehicle selection, and travel coordination in Sri Lanka.')

@section('content')
<main id="main-content" class="content-page">
  <div class="content-stage">
    <header class="content-hero" aria-labelledby="page-title">
      <a class="back-link" href="{{ route('home') }}">Home</a>
      <h1 id="page-title" tabindex="-1">Help &amp; Frequently Asked Questions</h1>
      <p class="content-lead">Essential information on how our advance transport booking, vehicle selection, and operations work across Sri Lanka.</p>
    </header>

    <section class="accordion faq-section" aria-labelledby="faq-heading">
      <h2 id="faq-heading" class="sr-only">Frequently Asked Questions</h2>

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
        <summary class="faq-question">What is included with Airport Pickup?</summary>
        <div class="faq-answer">
          <p>Airport Pickup includes flight arrival monitoring, meet-and-greet in the airport arrival terminal, luggage handling assistance, and chauffeured transport directly to your destination.</p>
        </div>
      </details>

      <details class="faq-item">
        <summary class="faq-question">How are luggage capacities determined?</summary>
        <div class="faq-answer">
          <p>Luggage capacity is verified for each vehicle based on standard international checked luggage and cabin bags. If you carry oversize sporting equipment (such as surfboards), please review vehicle space before submitting your request.</p>
        </div>
      </details>

      <details class="faq-item">
        <summary class="faq-question">What is required for self-drive Rent?</summary>
        <div class="faq-answer">
          <p>For self-drive Rent, drivers must present an original national driver's license, an International Driving Permit (IDP) or Sri Lankan driving endorsement, and passport identification in person at vehicle handover. Documents are never uploaded online.</p>
        </div>
      </details>

      <details class="faq-item">
        <summary class="faq-question">Why does every request remain pending?</summary>
        <div class="faq-answer">
          <p>Every request is personally evaluated by our operations team to confirm route feasibility, maintenance schedules, and turnaround buffers. You will receive an operational proposal confirming exact availability and costs before payment.</p>
        </div>
      </details>

      <details class="faq-item">
        <summary class="faq-question">How do changes and cancellations work?</summary>
        <div class="faq-answer">
          <p>Because bookings are coordinated with dedicated staff, changes and cancellations are managed directly with our operations desk. If details change, an updated proposal will be issued for your approval.</p>
        </div>
      </details>
    </section>

    <section class="closing-section" aria-labelledby="help-contact-title">
      <h2 id="help-contact-title">Have further questions?</h2>
      <p>Our operations desk is available to assist with advance travel planning and custom itineraries.</p>
      <a class="text-action" href="{{ route('contact') }}">Contact operations desk</a>
    </section>

    @include('partials.footer')
  </div>
</main>
@endsection

