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
        <h2 id="online-booking-title">Phased Service Launch</h2>
        <p>Online instant booking opens in phased releases:</p>
        <ul class="release-timeline">
          <li><strong>Phase 07:</strong> Airport Pickup (Arrivals with meet-and-greet)</li>
          <li><strong>Phase 08:</strong> Airport Transfer (Departures with scheduled pickup)</li>
          <li><strong>Phase 09:</strong> Ride (Chauffeured multi-stop itineraries)</li>
          <li><strong>Phase 10:</strong> Rent (Self-drive vehicle rental)</li>
        </ul>
        <p>Until online checkout is enabled for each respective service, advance inquiries can be submitted directly.</p>
      </section>

      <section class="contact-info-card" aria-labelledby="channels-title">
        <h2 id="channels-title">Operations Support</h2>
        <div class="pending-notice" role="status">
          <h3>Contact Details Verification</h3>
          <p>Direct client phone numbers, WhatsApp contact lines, and office hours are currently being finalized with the local operating business per Checklist Section 1. Verified contact details will appear here as soon as client sign-off is completed.</p>
        </div>
        <p>For questions regarding our four services or vehicle specifications, please review our service overview.</p>
        <a class="primary-action" href="{{ route('home') }}#services">View available services</a>
      </section>
    </div>

    @include('partials.footer')
  </div>
</main>
@endsection

