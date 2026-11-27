<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ContactType;
use App\Domain\Booking\Enums\ReviewStatus;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Booking\ServiceType;
use App\Domain\Offers\Services\ReviewService;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\OutboxEvent;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->lifecycleService = app(BookingLifecycleService::class);
    $this->reviewService = app(ReviewService::class);
});

test('completing a confirmed booking transitions status to completed, creates review invitation, and queues outbox event', function (): void {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'service_type' => ServiceType::AirportPickup,
    ]);

    BookingContact::factory()->create([
        'booking_id' => $booking->id,
        'type' => ContactType::LeadTraveler,
        'first_name' => 'Dr. Aris',
        'last_name' => 'Thorne',
        'email' => 'aris@example.com',
        'phone' => '+447700900077',
    ]);

    $completed = $this->lifecycleService->complete($booking);

    expect($completed->status)->toBe(BookingStatus::Completed);

    // Verify Review record created
    $review = Review::where('booking_id', $booking->id)->first();
    expect($review)->not->toBeNull()
        ->and($review->traveler_name)->toBe('Dr. Aris Thorne')
        ->and($review->status)->toBe(ReviewStatus::PendingModeration)
        ->and($review->verified_trip)->toBeTrue()
        ->and($review->invitation_token)->not->toBeEmpty()
        ->and($review->invited_at)->not->toBeNull();

    // Verify outbox message recorded
    $outbox = OutboxEvent::where('event_type', 'booking.review_invited')
        ->where('aggregate_id', $booking->id)
        ->first();

    expect($outbox)->not->toBeNull()
        ->and($outbox->payload['reference'])->toBe($booking->reference)
        ->and($outbox->payload['review_url'])->toContain($review->invitation_token);
});

test('cannot complete booking if status is not confirmed', function (): void {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Submitted,
    ]);

    expect(fn () => $this->lifecycleService->complete($booking))
        ->toThrow(InvalidArgumentException::class, 'Must be confirmed');
});

test('traveler can view review form and submit feedback via valid invitation token', function (): void {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
    ]);

    BookingContact::factory()->create([
        'booking_id' => $booking->id,
        'type' => ContactType::LeadTraveler,
        'first_name' => 'Elena',
        'last_name' => 'Rostova',
    ]);

    $this->lifecycleService->complete($booking);

    $review = Review::where('booking_id', $booking->id)->firstOrFail();

    // Form page renders
    $this->get(route('reviews.submit', $review->invitation_token))
        ->assertOk()
        ->assertSee('Elena Rostova')
        ->assertSee($booking->reference)
        ->assertSee('Overall Journey Rating');

    // Submit review
    $response = $this->post(route('reviews.store', $review->invitation_token), [
        'rating' => 5,
        'traveler_name' => 'Elena R.',
        'title' => 'Flawless arrival in Colombo',
        'review_text' => 'The chauffeur was waiting with a clear nameboard at CMB. Highly professional ride to Galle.',
    ]);

    $response->assertRedirect(route('reviews.submit', $review->invitation_token));

    $review->refresh();
    expect($review->rating)->toBe(5)
        ->and($review->traveler_name)->toBe('Elena R.')
        ->and($review->title)->toBe('Flawless arrival in Colombo')
        ->and($review->review_text)->toContain('chauffeur was waiting')
        ->and($review->submitted_at)->not->toBeNull()
        ->and($review->status)->toBe(ReviewStatus::PendingModeration);

    // Cannot submit again
    $secondAttempt = $this->post(route('reviews.store', $review->invitation_token), [
        'rating' => 4,
        'traveler_name' => 'Elena R.',
        'review_text' => 'Attempting duplicate submission.',
    ]);
    $secondAttempt->assertRedirect(route('reviews.submit', $review->invitation_token));
    $secondAttempt->assertSessionHas('info');
});

test('invalid review invitation token returns 404', function (): void {
    $this->get(route('reviews.submit', 'non-existent-token-12345'))
        ->assertNotFound();

    $this->post(route('reviews.store', 'non-existent-token-12345'), [
        'rating' => 5,
        'traveler_name' => 'Ghost',
        'review_text' => 'Invalid attempt',
    ])->assertNotFound();
});

test('homepage only displays verified reviews when published and featured', function (): void {
    $booking = Booking::factory()->create(['status' => BookingStatus::Completed]);

    // Review pending moderation: should NOT appear on homepage
    $pendingReview = Review::factory()->create([
        'booking_id' => $booking->id,
        'traveler_name' => 'Pending Traveler',
        'rating' => 5,
        'review_text' => 'Pending feedback should not be public.',
        'status' => ReviewStatus::PendingModeration,
        'is_featured' => true,
    ]);

    $home = $this->get(route('home'));
    $home->assertOk()
        ->assertDontSee('Pending Traveler')
        ->assertDontSee('Traveler Experiences'); // Section completely omitted when no published featured reviews

    // Moderate and publish review
    $pendingReview->update([
        'status' => ReviewStatus::Published,
        'is_featured' => true,
        'submitted_at' => Carbon::now('Asia/Colombo'),
    ]);

    $homePublished = $this->get(route('home'));
    $homePublished->assertOk()
        ->assertSee('Traveler Experiences')
        ->assertSee('Pending Traveler')
        ->assertSee('Pending feedback should not be public.')
        ->assertSee('★ 4.9 on Trustpilot');
});
