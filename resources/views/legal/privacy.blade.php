@extends('layouts.app')

@section('title', 'Privacy Policy — Spinellanka')
@section('meta_description', 'Privacy Policy and data protection standards for Spinellanka.')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<main id="main-content" class="content-page">
  <div class="content-stage">
    <header class="content-hero" aria-labelledby="page-title">
      <a class="back-link" href="{{ route('home') }}">Home</a>
      <h1 id="page-title" tabindex="-1">Privacy Policy</h1>
      <p class="content-lead">Our data minimization principles and privacy commitments.</p>
    </header>

    <div class="legal-content">
      <section class="legal-pending-notice" role="status">
        <h2>Policy Finalization in Progress</h2>
        <p>Our comprehensive privacy policy and data governance practices are currently being finalized with legal counsel. Complete policy documentation will be published prior to service launch.</p>
        <h3>Data Minimization Standards</h3>
        <p>Spinellanka operates under strict data minimization standards:</p>
        <ul>
          <li>We collect only the contact information strictly required to coordinate your transport.</li>
          <li>No customer account or login credentials are required or stored.</li>
          <li>Customer personal information is never sold or disclosed to third-party advertisers.</li>
          <li>Physical identification for self-drive Rent is inspected in person at vehicle handover and is not uploaded online.</li>
        </ul>
      </section>

      <div class="legal-actions">
        <a class="primary-action" href="{{ route('home') }}">Return to services</a>
        <a class="text-action" href="{{ route('contact') }}">Contact operations</a>
      </div>
    </div>

    @include('partials.footer')
  </div>
</main>
@endsection

