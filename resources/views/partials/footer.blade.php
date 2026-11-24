<footer class="site-footer">
  <div class="footer-summary">
    <strong>Spinellanka</strong>
    <p>Advance transport requests in Sri Lanka. Exact selectable vehicles, confirmed by staff.</p>
  </div>
  <nav class="footer-links" aria-label="Footer navigation">
    <a href="{{ route('home') }}#services">Services</a>
    <a href="{{ route('fleet.index') }}">Fleet</a>
    <a href="{{ route('how-it-works') }}">How It Works</a>
    <a href="{{ route('help') }}">Help &amp; FAQ</a>
    <a href="{{ route('contact') }}">Contact</a>
    <a href="{{ route('legal.terms') }}">Terms</a>
    <a href="{{ route('legal.privacy') }}">Privacy</a>
  </nav>
  <p class="footer-copy">&copy; {{ now()->year }} Spinellanka. All rights reserved.</p>
</footer>

