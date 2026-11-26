@extends('layouts.driver')

@section('title', 'Trip Inactive')

@section('content')
<div style="text-align: center; padding: 3rem 1rem;">
  <div style="width: 64px; height: 64px; margin: 0 auto 1.5rem auto; background-color: #f1f5f9; border-radius: 9999px; display: flex; align-items: center; justify-content: center; color: #64748b;">
    <svg style="width: 32px; height: 32px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
    </svg>
  </div>

  <h2 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;">
    Link Inactive or Expired
  </h2>

  <p style="font-size: 0.9375rem; color: #475569; line-height: 1.5; margin-bottom: 2rem;">
    This trip link is no longer active, has expired, or has been rotated by the dispatch desk.
  </p>

  <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.25rem; text-align: left;">
    <div style="font-weight: 600; font-size: 0.875rem; color: #1e293b; margin-bottom: 0.5rem;">
      Need Assistance?
    </div>
    <div style="font-size: 0.875rem; color: #64748b; line-height: 1.4;">
      If you are scheduled for a journey, please contact the Spinellanka operations desk directly via WhatsApp or telephone for a refreshed trip access link.
    </div>
  </div>
</div>
@endsection

