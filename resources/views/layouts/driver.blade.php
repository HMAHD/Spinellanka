<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0C1445">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="referrer" content="no-referrer">
  <title>@yield('title', 'Driver Trip Portal') | Spinellanka Operations</title>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    /* Standalone mobile optimizations for chauffeur devices */
    :root {
      --brand-navy: #0C1445;
      --brand-gold: #C5A880;
    }
    body {
      background-color: #f8fafc;
      color: #0f172a;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      margin: 0;
      padding: 0;
      -webkit-font-smoothing: antialiased;
    }
    .driver-shell {
      max-width: 480px;
      margin: 0 auto;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: #ffffff;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }
    .driver-header {
      background: var(--brand-navy);
      color: #ffffff;
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 2px solid var(--brand-gold);
    }
    .driver-header h1 {
      font-size: 1.125rem;
      font-weight: 700;
      margin: 0;
      letter-spacing: -0.01em;
    }
    .driver-header .badge {
      font-size: 0.75rem;
      font-weight: 600;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      background: rgba(255, 255, 255, 0.15);
      color: #f1f5f9;
    }
    .driver-main {
      flex: 1;
      padding: 1.25rem;
    }
    .driver-footer {
      padding: 1rem 1.25rem;
      text-align: center;
      font-size: 0.8125rem;
      color: #64748b;
      border-top: 1px solid #e2e8f0;
      background: #f8fafc;
    }
    /* Touch targets and dialog styling */
    .btn-touch {
      min-height: 48px;
      min-width: 48px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 1rem;
      border-radius: 0.5rem;
      text-decoration: none;
      cursor: pointer;
      transition: background-color 0.15s ease, transform 0.1s ease;
      touch-action: manipulation;
    }
    .btn-touch:active {
      transform: scale(0.98);
    }
    /* Native modal dialog styling */
    dialog:modal {
      overscroll-behavior: contain;
      border: none;
      border-radius: 1rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      padding: 1.5rem;
      max-width: 90vw;
      width: 400px;
    }
    dialog::backdrop {
      background-color: rgba(12, 20, 69, 0.6);
      backdrop-filter: blur(3px);
      overflow: hidden;
      overscroll-behavior: contain;
    }
  </style>
</head>
<body>
  <div class="driver-shell">
    <header class="driver-header" role="banner">
      <div>
        <h1>Spinellanka Operations</h1>
        <div style="font-size: 0.75rem; color: #94a3b8;">Chauffeur Mobile Portal</div>
      </div>
      <div class="badge" aria-label="Security: Ephemeral encrypted token">Secure Link</div>
    </header>

    <main class="driver-main" id="main-content" role="main">
      @if (session('status_success'))
        <div style="background-color: #ecfdf5; border-left: 4px solid #10b981; color: #065f46; padding: 0.875rem 1rem; border-radius: 0.375rem; margin-bottom: 1.25rem; font-size: 0.9375rem;" role="alert">
          {{ session('status_success') }}
        </div>
      @endif

      @if (session('status_error'))
        <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; padding: 0.875rem 1rem; border-radius: 0.375rem; margin-bottom: 1.25rem; font-size: 0.9375rem;" role="alert">
          {{ session('status_error') }}
        </div>
      @endif

      @if ($errors->any())
        <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; padding: 0.875rem 1rem; border-radius: 0.375rem; margin-bottom: 1.25rem; font-size: 0.9375rem;" role="alert">
          {{ $errors->first() }}
        </div>
      @endif

      @yield('content')
    </main>

    <footer class="driver-footer" role="contentinfo">
      <div>Spinellanka Mobility Private Limited</div>
      <div style="margin-top: 0.25rem;">Colombo Fleet Desk: +94 11 234 5678</div>
    </footer>
  </div>

  @yield('scripts')
</body>
</html>

