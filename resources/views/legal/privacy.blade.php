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
        <h2>Policy Verification in Progress</h2>
        <p>Our official privacy notice and data protection terms are currently being finalized with authorized legal counsel. Complete verified terms will be published prior to service launch.</p>
        <p>In the interim, Spinellanka operates on a no-account model: customer transport inquiries are handled directly with operations without public account creation or third-party tracking.</p>
      </section>

      <div class="legal-actions">
        <a class="primary-action" href="{{ route('home') }}">Return to services</a>
        <a class="text-action" href="{{ route('contact') }}">Contact operations</a>
      </div>
    </div>
  </div>
  @include('partials.footer')
</main>
@endsection
