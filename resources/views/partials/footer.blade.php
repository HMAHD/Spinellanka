<footer class="site-footer">
  <div class="site-footer-inner">
    <div class="footer-lead">
      <a class="footer-brand" href="{{ route('home') }}" aria-label="Spinellanka home">
        <svg class="footer-brand-mark" width="32" height="32" viewBox="0 0 28 28" fill="none" aria-hidden="true">
          <rect width="28" height="28" rx="7" fill="#18235D"/>
          <path d="M14 5L21 14L14 23L7 14L14 5Z" fill="#FBBF24"/>
          <circle cx="14" cy="14" r="3" fill="#0C1445"/>
        </svg>
        <span class="footer-brand-wordmark">Spinel<span>lanka</span></span>
      </a>
      <p>Advance transport desk for Sri Lanka. Select your exact vehicle, inspect itemized estimates up front, and confirm every request with our operations team.</p>

      <ul class="footer-assurances" aria-label="Service guarantees">
        <li>
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <path d="M8 1.5L13.5 3.7V7.8C13.5 11.1 11.2 13.7 8 14.5C4.8 13.7 2.5 11.1 2.5 7.8V3.7L8 1.5Z"/>
            <path d="M5.7 8.1L7.3 9.7L10.5 6.4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span>Exact vehicle allocation</span>
        </li>
        <li>
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <circle cx="8" cy="8" r="6"/>
            <path d="M8 4.8V8L10.3 9.4" stroke-linecap="round"/>
          </svg>
          <span>Asia/Colombo schedule authority</span>
        </li>
        <li>
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <rect x="2.2" y="3.2" width="11.6" height="9.6" rx="2"/>
            <path d="M5 7H11M5 9.6H9" stroke-linecap="round"/>
          </svg>
          <span>Pending until staff confirmation</span>
        </li>
      </ul>
    </div>

    <div class="footer-directory">
      <nav aria-labelledby="footer-services-title">
        <h2 id="footer-services-title">Services</h2>
        <ul>
          <li><a href="{{ route('services.show', ['service' => 'airport_pickup']) }}">Airport Pickup</a></li>
          <li><a href="{{ route('services.show', ['service' => 'airport_transfer']) }}">Airport Transfer</a></li>
          <li><a href="{{ route('services.show', ['service' => 'ride']) }}">Chauffeured Ride</a></li>
          <li><a href="{{ route('services.show', ['service' => 'rent']) }}">Self-Drive Rent</a></li>
        </ul>
      </nav>

      <nav aria-labelledby="footer-deals-title">
        <h2 id="footer-deals-title">Curated Deals</h2>
        <ul>
          <li><a href="{{ route('deals.index') }}">All Packages</a></li>
          <li><a href="{{ route('deals.index', ['service' => 'airport_pickup']) }}">Pickup Packages</a></li>
          <li><a href="{{ route('deals.index', ['service' => 'airport_transfer']) }}">Transfer Packages</a></li>
          <li><a href="{{ route('deals.index', ['service' => 'ride']) }}">Touring Rides</a></li>
          <li><a href="{{ route('deals.index', ['service' => 'rent']) }}">Self-Drive Deals</a></li>
        </ul>
      </nav>

      <nav aria-labelledby="footer-company-title">
        <h2 id="footer-company-title">Plan &amp; Support</h2>
        <ul>
          <li><a href="{{ route('fleet.index') }}">Our Fleet</a></li>
          <li><a href="{{ route('how-it-works') }}">How It Works</a></li>
          <li><a href="{{ route('help') }}">Help &amp; FAQ</a></li>
          <li><a href="{{ route('contact') }}">Contact Us</a></li>
        </ul>
      </nav>

      <nav aria-labelledby="footer-legal-title">
        <h2 id="footer-legal-title">Trust &amp; Legal</h2>
        <ul>
          <li><a href="{{ route('legal.terms') }}">Terms of Service</a></li>
          <li><a href="{{ route('legal.privacy') }}">Privacy Notice</a></li>
          @if(
            config('services.analytics.provider') === 'plausible'
            && filled(config('services.analytics.plausible_domain'))
            && request()->route() !== null
            && request()->routeIs('home', 'services.*', 'fleet.*', 'deals.*', 'how-it-works', 'help', 'contact', 'legal.*')
          )
            <li>
              <button type="button" class="footer-consent-reset" data-analytics-manage hidden>
                Analytics settings
              </button>
            </li>
          @endif
        </ul>
      </nav>
    </div>

    <div class="footer-baseline">
      <p>&copy; {{ now()->year }} Spinellanka. All rights reserved.</p>
      <p>Advance-booked transport in Sri Lanka. Every request stays pending until confirmed by staff.</p>
    </div>
  </div>
</footer>
