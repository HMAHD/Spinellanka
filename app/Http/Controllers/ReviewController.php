<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService
    ) {}

    /**
     * Show customer review submission form for a completed journey.
     */
    public function create(string $token): Response
    {
        $review = $this->reviewService->findByToken($token);

        if ($review === null) {
            abort(404, 'Review invitation link is invalid or expired.');
        }

        return response()->view('reviews.submit', [
            'review' => $review,
            'booking' => $review->booking,
            'alreadySubmitted' => $review->submitted_at !== null,
        ]);
    }

    /**
     * Ingest customer rating and feedback into moderation queue.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $review = $this->reviewService->findByToken($token);

        if ($review === null) {
            abort(404, 'Review invitation link is invalid or expired.');
        }

        if ($review->submitted_at !== null) {
            return redirect()->route('reviews.submit', $token)
                ->with('info', 'Your feedback for this journey has already been received. Thank you!');
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'traveler_name' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:120'],
            'review_text' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        try {
            $this->reviewService->submitReview(
                token: $token,
                rating: (int) $validated['rating'],
                travelerName: (string) $validated['traveler_name'],
                title: isset($validated['title']) ? (string) $validated['title'] : null,
                reviewText: (string) $validated['review_text']
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('reviews.submit', $token)
            ->with('success', 'Thank you! Your feedback has been received and submitted for verified trip publication.');
    }
}
