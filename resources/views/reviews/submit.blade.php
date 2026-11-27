@extends('layouts.app')

@section('title', 'Share Your Experience — Spinellanka Verified Reviews')
@section('meta_description', 'Submit your post-trip feedback for your completed Spinellanka journey. Verified traveler feedback helps us maintain exceptional chauffeured and self-drive standards.')

@section('content')
<main id="main-content" class="content-page review-submission-page">
  <div class="content-stage review-stage">
    <header class="content-hero review-hero">
      <h1 id="page-title" tabindex="-1">Verified Trip Feedback</h1>
      <p class="content-lead">Thank you for traveling with Spinellanka. As part of our verified traveler standard, your feedback directly informs fleet quality and staff performance.</p>
    </header>

    @if(session('success'))
      <div class="alert alert-success" role="status">
        <h2>Feedback Received</h2>
        <p>{{ session('success') }}</p>
        <div class="alert-actions">
          <a href="https://www.trustpilot.com/review/spinellanka.com" target="_blank" rel="noopener noreferrer" class="primary-action">
            Share on Trustpilot &rsaquo;
          </a>
        </div>
      </div>
    @elseif(session('info') || $alreadySubmitted)
      <div class="alert alert-info" role="status">
        <div class="alert-check" aria-hidden="true">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 6L9 17L4 12"/>
          </svg>
        </div>
        <h2>Review Already Recorded</h2>
        <p>
          {{ session('info') ?? 'Your feedback for this journey has already been recorded. We sincerely appreciate your time.' }}
        </p>
        <a href="https://www.trustpilot.com/review/spinellanka.com" target="_blank" rel="noopener noreferrer" class="text-action">
          View Spinellanka on Trustpilot &rsaquo;
        </a>
      </div>
    @else
      <div class="review-form-card">
        <div class="trip-context-box">
          <div>
            <span class="trip-context-label">Confirmed Trip</span>
            <strong class="trip-context-value">{{ $booking->reference }}</strong>
          </div>
          <div>
            <span class="trip-context-label">Service</span>
            <span class="trip-context-value">{{ $booking->service_type->label() }}</span>
          </div>
          <div>
            <span class="verified-tag">Verified Traveler</span>
          </div>
        </div>

        @if(session('error'))
          <div class="alert alert-error" role="alert">
            {{ session('error') }}
          </div>
        @endif

        <form action="{{ route('reviews.store', $review->invitation_token) }}" method="POST" novalidate>
          @csrf

          <div class="form-group">
            <label for="rating">
              Overall Journey Rating <span class="required">*</span>
            </label>
            <select id="rating" name="rating" required class="form-input">
              <option value="5" {{ old('rating', 5) == 5 ? 'selected' : '' }}>5 Stars — Excellent / Highly Satisfied</option>
              <option value="4" {{ old('rating') == 4 ? 'selected' : '' }}>4 Stars — Very Good / Met Expectations</option>
              <option value="3" {{ old('rating') == 3 ? 'selected' : '' }}>3 Stars — Average / Satisfactory</option>
              <option value="2" {{ old('rating') == 2 ? 'selected' : '' }}>2 Stars — Disappointing / Needs Improvement</option>
              <option value="1" {{ old('rating') == 1 ? 'selected' : '' }}>1 Star — Poor Experience</option>
            </select>
            @error('rating')
              <span class="form-error">{{ $message }}</span>
            @enderror
          </div>

          <div class="form-group">
            <label for="traveler_name">
              Your Name (as displayed publicly) <span class="required">*</span>
            </label>
            <input type="text"
                   id="traveler_name"
                   name="traveler_name"
                   value="{{ old('traveler_name', $review->traveler_name) }}"
                   required
                   class="form-input">
            <small class="form-hint">E.g. Sarah M. or James Thornton.</small>
            @error('traveler_name')
              <span class="form-error">{{ $message }}</span>
            @enderror
          </div>

          <div class="form-group">
            <label for="title">
              Headline / Summary (Optional)
            </label>
            <input type="text"
                   id="title"
                   name="title"
                   value="{{ old('title') }}"
                   placeholder="e.g. Punctual airport pickup and pristine sedan"
                   class="form-input">
            @error('title')
              <span class="form-error">{{ $message }}</span>
            @enderror
          </div>

          <div class="form-group">
            <label for="review_text">
              Your Detailed Experience <span class="required">*</span>
            </label>
            <textarea id="review_text"
                      name="review_text"
                      rows="5"
                      required
                      minlength="10"
                      maxlength="2000"
                      placeholder="Share details regarding vehicle cleanliness, chauffeur professionalism, timing, communication, or handover..."
                      class="form-input">{{ old('review_text') }}</textarea>
            <small class="form-hint">Minimum 10 characters. Reviews are moderated for authentic traveler compliance.</small>
            @error('review_text')
              <span class="form-error">{{ $message }}</span>
            @enderror
          </div>

          <button type="submit" class="primary-action review-submit-button">
            Submit Verified Review &rsaquo;
          </button>
        </form>
      </div>
    @endif
  </div>
  @include('partials.footer')
</main>
@endsection

