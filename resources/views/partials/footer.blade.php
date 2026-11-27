<footer class="site-footer">
  <div class="site-footer-inner">
    <div class="footer-lead">
      <a class="footer-brand" href="{{ route('home') }}" aria-label="Spinellanka home">
        <span aria-hidden="true">Spinel</span><span aria-hidden="true">lanka</span>
      </a>
      <p>Advance transport requests in Sri Lanka, with the exact vehicle selected and every request confirmed by staff.</p>
    </div>

    <div class="footer-directory">
      <nav aria-labelledby="footer-plan-title">
        <h2 id="footer-plan-title">Plan</h2>
        <ul>
          <li><a href="{{ route('home') }}#service-desk">Services</a></li>
          <li><a href="{{ route('fleet.index') }}">Fleet</a></li>
          <li><a href="{{ route('how-it-works') }}">How It Works</a></li>
        </ul>
      </nav>

      <nav aria-labelledby="footer-support-title">
        <h2 id="footer-support-title">Support</h2>
        <ul>
          <li><a href="{{ route('help') }}">Help &amp; FAQ</a></li>
          <li><a href="{{ route('contact') }}">Contact</a></li>
        </ul>
      </nav>

      <nav aria-labelledby="footer-legal-title">
        <h2 id="footer-legal-title">Legal</h2>
        <ul>
          <li><a href="{{ route('legal.terms') }}">Terms</a></li>
          <li><a href="{{ route('legal.privacy') }}">Privacy</a></li>
          @if(
            config('services.analytics.provider') === 'plausible'
            && filled(config('services.analytics.plausible_domain'))
            && request()->route() !== null
            && request()->routeIs('home', 'services.*', 'fleet.*', 'how-it-works', 'help', 'contact', 'legal.*')
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
      <p>Advance-booked transport. Requests remain pending until staff confirmation.</p>
    </div>
  </div>
</footer>
