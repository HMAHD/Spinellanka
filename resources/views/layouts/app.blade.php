<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0C1445">
  <meta name="description" content="@yield('meta_description', 'Advance-booking transport services for international travelers in Sri Lanka.')">

  <title>@yield('title', config('app.name'))</title>

  <script>document.documentElement.classList.add('js')</script>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
  <a class="skip-link" href="#page-title">Skip to content</a>

  <header class="site-header" data-site-header>
    <a class="brand" href="{{ route('home') }}" aria-label="Spinellanka home">
      <span aria-hidden="true">Spinel</span><span aria-hidden="true">lanka</span>
    </a>

    <button class="menu-trigger" type="button" popovertarget="site-menu" aria-controls="site-menu">
      Menu
    </button>

    <nav class="site-nav" id="site-menu" aria-label="Primary navigation">
      <a href="{{ route('home') }}#services" @if(request()->routeIs('home')) aria-current="page" @endif>Services</a>
      <a href="{{ route('home') }}#process">Process</a>
      <a class="nav-action" href="{{ route('home') }}#service-desk">Choose service</a>
    </nav>
  </header>

  @yield('content')
</body>
</html>
