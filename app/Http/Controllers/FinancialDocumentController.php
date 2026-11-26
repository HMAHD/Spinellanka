<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\FinancialDocument;
use App\Models\GuestAccessToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FinancialDocumentController extends Controller
{
    /**
     * Guest download/view of an official financial document.
     */
    public function download(Request $request, string $reference, string $token, string $documentNumber): Response
    {
        $tokenHash = GuestAccessToken::hashToken($token);

        /** @var GuestAccessToken|null $accessToken */
        $accessToken = GuestAccessToken::where('token_hash', $tokenHash)->first();

        if (! $accessToken || ! $accessToken->isValid()) {
            abort(404);
        }

        /** @var Booking|null $booking */
        $booking = Booking::where('id', $accessToken->booking_id)
            ->where('reference', $reference)
            ->first();

        if (! $booking) {
            abort(404);
        }

        /** @var FinancialDocument|null $document */
        $document = FinancialDocument::where('booking_id', $booking->id)
            ->where('document_number', $documentNumber)
            ->first();

        if (! $document) {
            abort(404);
        }

        return $this->renderDocumentFile($document);
    }

    /**
     * Staff authenticated view of a financial document.
     */
    public function staffView(Request $request, Booking $booking, string $documentNumber): Response
    {
        /** @var User|null $staff */
        $staff = Auth::user();

        if (! $staff || (! $staff->can('payment.view') && ! $staff->can('booking.view'))) {
            abort(403, 'Unauthorized to view financial documents.');
        }

        /** @var FinancialDocument|null $document */
        $document = FinancialDocument::where('booking_id', $booking->id)
            ->where('document_number', $documentNumber)
            ->first();

        if (! $document) {
            abort(404);
        }

        return $this->renderDocumentFile($document);
    }

    protected function renderDocumentFile(FinancialDocument $document): Response
    {
        $filePath = 'private/'.$document->file_path;

        if (! Storage::disk('local')->exists($filePath)) {
            abort(404, 'Document file not found on disk.');
        }

        $content = Storage::disk('local')->get($filePath);

        return response($content, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
