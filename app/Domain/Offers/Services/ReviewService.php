<?php

declare(strict_types=1);

namespace App\Domain\Offers\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\Enums\ReviewStatus;
use App\Domain\Booking\ServiceType;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReviewService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Retrieve featured published reviews for homepage social proof.
     *
     * @return Collection<int, Review>
     */
    public function getFeaturedReviews(int $limit = 6): Collection
    {
        return Review::query()
            ->with(['booking'])
            ->where('status', ReviewStatus::Published)
            ->where('is_featured', true)
            ->whereNotNull('review_text')
            ->orderByDesc('rating')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Retrieve published verified reviews archive.
     *
     * @return Collection<int, Review>
     */
    public function getPublishedReviews(?ServiceType $serviceType = null, int $limit = 20): Collection
    {
        $query = Review::query()
            ->with(['booking'])
            ->where('status', ReviewStatus::Published)
            ->whereNotNull('review_text');

        if ($serviceType !== null) {
            $query->where('service_type', $serviceType->value);
        }

        return $query->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Locate a review record by its secure invitation token.
     */
    public function findByToken(string $token): ?Review
    {
        return Review::with(['booking'])->where('invitation_token', $token)->first();
    }

    /**
     * Ingest customer-submitted feedback and rating into the moderation queue.
     */
    public function submitReview(
        string $token,
        int $rating,
        string $travelerName,
        ?string $title,
        string $reviewText
    ): Review {
        return DB::transaction(function () use ($token, $rating, $travelerName, $title, $reviewText) {
            $review = Review::where('invitation_token', $token)->lockForUpdate()->first();

            if ($review === null) {
                throw new InvalidArgumentException('Invalid or expired review invitation token.');
            }

            if ($review->submitted_at !== null) {
                throw new InvalidArgumentException('Feedback has already been submitted for this journey.');
            }

            if ($rating < 1 || $rating > 5) {
                throw new InvalidArgumentException('Rating must be an integer between 1 and 5.');
            }

            $review->update([
                'rating' => $rating,
                'traveler_name' => trim($travelerName),
                'title' => $title ? trim($title) : null,
                'review_text' => trim($reviewText),
                'submitted_at' => Carbon::now('Asia/Colombo'),
                'status' => ReviewStatus::PendingModeration,
            ]);

            $this->auditService->record(
                action: 'review.submitted',
                actor: null,
                summary: "Review #{$review->id} submitted by traveler {$review->traveler_name} (Rating: {$rating}/5)",
                metadata: [
                    'review_id' => $review->id,
                    'booking_id' => $review->booking_id,
                    'rating' => $rating,
                    'service_type' => $review->service_type->value,
                ],
                auditable: $review,
                actorType: 'guest'
            );

            return $review;
        });
    }

    /**
     * Moderate a review: approve, feature, or reject.
     */
    public function moderateReview(
        Review $review,
        ReviewStatus $status,
        bool $isFeatured = false,
        ?string $notes = null,
        ?User $moderator = null
    ): Review {
        $review->update([
            'status' => $status,
            'is_featured' => $isFeatured,
            'moderation_notes' => $notes ? trim($notes) : null,
            'moderated_by_user_id' => $moderator?->id,
            'moderated_at' => Carbon::now('Asia/Colombo'),
        ]);

        $this->auditService->record(
            action: 'review.moderated',
            actor: $moderator,
            summary: "Review #{$review->id} moderated: {$status->value}".($isFeatured ? ' [Featured]' : ''),
            metadata: [
                'review_id' => $review->id,
                'booking_id' => $review->booking_id,
                'status' => $status->value,
                'is_featured' => $isFeatured,
                'moderator_id' => $moderator?->id,
            ],
            auditable: $review,
            actorType: $moderator ? 'staff' : 'system'
        );

        return $review;
    }
}
