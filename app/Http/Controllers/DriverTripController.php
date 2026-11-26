<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Operations\Enums\DriverTripStatus;
use App\Domain\Operations\Services\DriverTripService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class DriverTripController extends Controller
{
    public function __construct(
        protected DriverTripService $driverTripService,
    ) {}

    /**
     * Display the minimal mobile driver trip page or neutral privacy shell.
     */
    public function show(Request $request, string $token): Response
    {
        $tokenRecord = $this->driverTripService->validateToken($token);

        if (! $tokenRecord) {
            return $this->neutralPrivacyShell();
        }

        $tripViewData = $this->driverTripService->getTripViewData($tokenRecord);

        return response()
            ->view('driver.trip', [
                'trip' => $tripViewData,
                'rawToken' => $token,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    /**
     * Advance trip milestone monotonically and idempotently.
     */
    public function updateStatus(Request $request, string $token): Response|RedirectResponse
    {
        $tokenRecord = $this->driverTripService->validateToken($token);

        if (! $tokenRecord) {
            return $this->neutralPrivacyShell();
        }

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_timestamp' => ['nullable', 'string', 'max:64'],
        ]);

        $newStatus = DriverTripStatus::tryFrom($validated['status']);

        if (! $newStatus) {
            return redirect()->route('driver.trip.show', ['token' => $token])
                ->with('status_error', 'Invalid status transition requested.');
        }

        try {
            $this->driverTripService->updateTripStatus(
                tokenRecord: $tokenRecord,
                newStatus: $newStatus,
                notes: $validated['notes'] ?? null,
                clientTimestamp: $validated['client_timestamp'] ?? null,
            );

            return redirect()->route('driver.trip.show', ['token' => $token])
                ->with('status_success', "Trip updated: {$newStatus->label()}");
        } catch (InvalidArgumentException $e) {
            return redirect()->route('driver.trip.show', ['token' => $token])
                ->with('status_error', $e->getMessage());
        }
    }

    /**
     * Report an operational issue without interrupting the milestone progression.
     */
    public function reportIssue(Request $request, string $token): Response|RedirectResponse
    {
        $tokenRecord = $this->driverTripService->validateToken($token);

        if (! $tokenRecord) {
            return $this->neutralPrivacyShell();
        }

        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:3', 'max:1000'],
            'client_timestamp' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $this->driverTripService->reportIssue(
                tokenRecord: $tokenRecord,
                notes: $validated['notes'],
                clientTimestamp: $validated['client_timestamp'] ?? null,
            );

            return redirect()->route('driver.trip.show', ['token' => $token])
                ->with('status_success', 'Operational note dispatched to fleet desk.');
        } catch (InvalidArgumentException $e) {
            return redirect()->route('driver.trip.show', ['token' => $token])
                ->with('status_error', $e->getMessage());
        }
    }

    /**
     * Uniform neutral privacy shell revealing zero booking existence.
     */
    protected function neutralPrivacyShell(): Response
    {
        return response()
            ->view('driver.invalid', [], 404)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }
}
