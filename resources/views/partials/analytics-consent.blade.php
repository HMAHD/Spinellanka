@php
  $isPublicAnalyticsSurface =
      config('services.analytics.provider') === 'plausible'
      && filled(config('services.analytics.plausible_domain'))
      && request()->route() !== null
      && request()->routeIs('home', 'services.*', 'fleet.*', 'how-it-works', 'help', 'contact', 'legal.*');
@endphp

@if($isPublicAnalyticsSurface)
  <aside
    class="analytics-consent-bar"
    id="analytics-consent-bar"
    role="region"
    aria-label="Analytics privacy choice"
    data-analytics-domain="{{ config('services.analytics.plausible_domain') }}"
    data-analytics-src="{{ config('services.analytics.plausible_script_url') }}"
    hidden
  >
    <p class="analytics-consent-copy">
      Optional cookieless page-view analytics help improve our public guides. Booking, payment, and private pages are never tracked.
    </p>
    <div class="analytics-consent-actions">
      <button type="button" class="analytics-btn-secondary" data-analytics-decline>Decline</button>
      <button type="button" class="analytics-btn-primary" data-analytics-accept>Accept</button>
    </div>
  </aside>
@endif

