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

  <script>document.documentElement.classList.add('js')</script>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
  <a class="skip-link" href="#page-title">Skip to content</a>

  <header class="site-header" data-site-header>
    <div class="site-header-inner">
      <a class="brand" href="{{ route('home') }}" aria-label="Spinellanka home">
        <span aria-hidden="true">Spinel</span><span aria-hidden="true">lanka</span>
      </a>

      <button class="menu-trigger" type="button" popovertarget="site-menu" popovertargetaction="toggle" aria-controls="site-menu">
        Menu
      </button>

      <nav class="site-nav" id="site-menu" aria-label="Primary">
        <a href="{{ route('home') }}#service-desk" @if(request()->routeIs('home', 'services.*')) aria-current="page" @endif>Services</a>
        <a href="{{ route('fleet.index') }}" @if(request()->routeIs('fleet.*')) aria-current="page" @endif>Fleet</a>
        <a href="{{ route('how-it-works') }}" @if(request()->routeIs('how-it-works')) aria-current="page" @endif>How It Works</a>
        <a href="{{ route('help') }}" @if(request()->routeIs('help')) aria-current="page" @endif>Help</a>
        <a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contact</a>
        <a class="nav-action" href="{{ route('home') }}#service-desk">Choose service</a>
      </nav>
    </div>
  </header>

  @yield('content')
  @include('partials.analytics-consent')
</body>
</html>
