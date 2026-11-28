@extends('layouts.app')

@section('title', 'Terms of Service — Spinellanka')
@section('meta_description', 'Terms of Service for Spinellanka transport services in Sri Lanka.')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<main id="main-content" class="content-page">
  <div class="content-stage">
    <header class="content-hero" aria-labelledby="page-title">
      <a class="back-link" href="{{ route('home') }}">Home</a>
      <h1 id="page-title" tabindex="-1">Terms of Service</h1>
      <p class="content-lead">Operating policies, carriage conditions, and customer agreements.</p>
    </header>

    <div class="legal-content">
      <section class="legal-pending-notice" role="status">
        <h2>Legal Verification in Progress</h2>
        <p>Terms of service, carriage conditions, passenger responsibilities, and self-drive rental agreements are currently being finalized with authorized legal counsel. Complete verified terms will be published prior to service launch.</p>
        <p>In the interim, all transport inquiries are subject to operational verification, proposal acceptance, and staff confirmation before any service commitment is made.</p>
      </section>

      <div class="legal-actions">
        <a class="primary-action" href="{{ route('home') }}">Return to services</a>
        <a class="text-action" href="{{ route('help') }}">Read Help &amp; FAQ</a>
      </div>
    </div>
  </div>
  @include('partials.footer')
</main>
@endsection

