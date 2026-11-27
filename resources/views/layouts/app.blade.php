<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0C1445">
  <meta name="robots" content="@yield('meta_robots', 'index, follow')">
  <meta name="referrer" content="no-referrer">
  <meta name="description" content="@yield('meta_description', 'Advance-booking transport services for international travelers in Sri Lanka.')">

  <title>@yield('title', config('app.name'))</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

  <script>document.documentElement.classList.add('js')</script>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
  <a class="skip-link" href="#page-title">Skip to content</a>

  <header class="site-header" data-site-header>
    <div class="site-header-inner">
      <a class="brand" href="{{ route('home') }}" aria-label="Spinellanka home">
        <svg class="brand-mark" width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
          <rect width="28" height="28" rx="7" fill="#0C1445"/>
          <path d="M14 5L21 14L14 23L7 14L14 5Z" fill="#FBBF24"/>
          <circle cx="14" cy="14" r="3" fill="#0C1445"/>
        </svg>
        <span class="brand-wordmark">Spinellanka</span>
      </a>

      <nav class="site-nav" aria-label="Primary">
        <button
          class="menu-button"
          type="button"
          popovertarget="site-menu"
          popovertargetaction="toggle"
          aria-controls="site-menu"
        >
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
            <path d="M2.5 5H15.5M2.5 9H15.5M2.5 13H15.5"/>
          </svg>
          <span>Menu</span>
        </button>

        @php
          $currentServiceParam = request()->route('service');
          $isServiceActive = static fn (string $slug): bool =>
              (request()->routeIs('services.show') && $currentServiceParam === $slug)
              || request()->routeIs('booking.'.str_replace('_', '-', $slug).'*');
        @endphp

        <ul id="site-menu" class="site-menu" popover="auto">
          <li>
            <a href="{{ route('services.show', ['service' => 'airport_pickup']) }}" @if($isServiceActive('airport_pickup')) aria-current="page" @endif>
              Airport Pickup
            </a>
          </li>
          <li>
            <a href="{{ route('services.show', ['service' => 'airport_transfer']) }}" @if($isServiceActive('airport_transfer')) aria-current="page" @endif>
              Airport Transfer
            </a>
          </li>
          <li>
            <a href="{{ route('services.show', ['service' => 'ride']) }}" @if($isServiceActive('ride')) aria-current="page" @endif>
              Ride
            </a>
          </li>
          <li>
            <a href="{{ route('services.show', ['service' => 'rent']) }}" @if($isServiceActive('rent')) aria-current="page" @endif>
              Rent
            </a>
          </li>
          <li>
            <a href="{{ route('deals.index') }}" @if(request()->routeIs('deals.*')) aria-current="page" @endif>
              Deals
            </a>
          </li>
          <li class="nav-dropdown" data-nav-dropdown>
            <button
              class="dropdown-trigger"
              type="button"
              aria-expanded="false"
              aria-controls="more-menu"
              popovertarget="more-menu"
            >
              <span>More</span>
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3.5 5.25L7 8.75L10.5 5.25"/>
              </svg>
            </button>
            <ul id="more-menu" class="dropdown-list">
              <li><a href="{{ route('fleet.index') }}" @if(request()->routeIs('fleet.*')) aria-current="page" @endif>Our Fleet</a></li>
              <li><a href="{{ route('how-it-works') }}" @if(request()->routeIs('how-it-works')) aria-current="page" @endif>How It Works</a></li>
              <li><a href="{{ route('help') }}" @if(request()->routeIs('help')) aria-current="page" @endif>Help &amp; FAQ</a></li>
              <li><a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contact</a></li>
            </ul>
          </li>
        </ul>
      </nav>
    </div>
  </header>

  @yield('content')
  @include('partials.analytics-consent')
</body>
</html>
