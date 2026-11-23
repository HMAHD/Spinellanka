@extends('layouts.app')

@section('title', 'Contact & Inquiries — Spinellanka')
@section('meta_description', 'Contact the Spinellanka operations desk for advance transport inquiries and travel coordination across Sri Lanka.')

@section('content')
<main id="main-content" class="content-page">
  <div class="content-stage">
    <header class="content-hero" aria-labelledby="page-title">
      <a class="back-link" href="{{ route('home') }}">Home</a>
      <h1 id="page-title" tabindex="-1">Contact &amp; Inquiries</h1>
      <p class="content-lead">Our operations desk coordinates advance travel bookings and custom transport requests across Sri Lanka.</p>
    </header>

    <div class="contact-layout">
      <section class="contact-info-card" aria-labelledby="online-booking-title">
        <h2 id="online-booking-title">Service Launch Sequence</h2>
        <p>Online instant booking is planned for sequential release across our four services:</p>
        <ul class="release-timeline">
          <li><strong>Airport Pickup:</strong> Arrival airport to destination with driver and meet-and-greet support</li>
          <li><strong>Airport Transfer:</strong> Origin to departure airport with driver and guided pickup timing</li>
          <li><strong>Ride:</strong> Scheduled point-to-point and multi-stop travel with driver</li>
          <li><strong>Rent:</strong> Exact self-drive vehicle hire without driver</li>
        </ul>
        <p>Until online checkout is enabled for each service, advance inquiries can be submitted directly.</p>
      </section>

      <section class="contact-info-card" aria-labelledby="channels-title">
        <h2 id="channels-title">Operations Support</h2>
        <div class="pending-notice" role="status">
          <h3>Contact Details Verification</h3>
          <p>Direct phone numbers, WhatsApp contact lines, and operating hours are currently being finalized with the local operator. Verified contact channels will appear here once confirmed.</p>
        </div>
        <p>For questions regarding our four services or vehicle specifications, please review our service overview.</p>
        <a class="primary-action" href="{{ route('home') }}#services">View available services</a>
      </section>
    </div>

    @include('partials.footer')
  </div>
</main>
@endsection
