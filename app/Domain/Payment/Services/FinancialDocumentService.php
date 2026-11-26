<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Payment\Enums\FinancialDocumentType;
use App\Models\Booking;
use App\Models\DocumentSequence;
use App\Models\FinancialDocument;
use App\Models\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class FinancialDocumentService
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    /**
     * Generate an official payment receipt for a completed transaction.
     */
    public function generateReceipt(PaymentTransaction $transaction): FinancialDocument
    {
        $transaction->loadMissing(['booking', 'proposal']);
        $now = Carbon::now('Asia/Colombo');
        $documentNumber = DocumentSequence::nextNumber(FinancialDocumentType::Receipt, (int) $now->year);

        $checksum = hash('sha256', sprintf(
            '%s|%d|%s|%d|%s',
            $documentNumber,
            $transaction->id,
            $transaction->currency,
            $transaction->amount_minor_units,
            $now->toIso8601String()
        ));

        $relativeFilePath = sprintf(
            'documents/%d/%s.html',
            $transaction->booking_id,
            $documentNumber
        );

        // Render printable HTML receipt into private storage
        $html = view('documents.receipt', [
            'booking' => $transaction->booking,
            'proposal' => $transaction->proposal,
            'transaction' => $transaction,
            'documentNumber' => $documentNumber,
            'issuedAt' => $now,
            'checksum' => $checksum,
        ])->render();

        Storage::disk('local')->put('private/'.$relativeFilePath, $html);

        /** @var FinancialDocument $document */
        $document = FinancialDocument::create([
            'booking_id' => $transaction->booking_id,
            'proposal_id' => $transaction->proposal_id,
            'payment_transaction_id' => $transaction->id,
            'document_type' => FinancialDocumentType::Receipt,
            'document_number' => $documentNumber,
            'version' => 1,
            'amount_minor_units' => $transaction->amount_minor_units,
            'currency' => $transaction->currency,
            'file_path' => $relativeFilePath,
            'checksum' => $checksum,
            'metadata' => [
                'payment_method' => $transaction->payment_method->value,
                'gateway_transaction_id' => $transaction->gateway_transaction_id,
            ],
            'issued_at' => $now,
        ]);

        $this->auditService->record(
            action: 'financial_document.issued',
            actor: null,
            summary: "Issued {$document->document_type->label()} {$document->document_number} for booking {$transaction->booking->reference}",
            metadata: [
                'booking_id' => $transaction->booking_id,
                'document_id' => $document->id,
                'document_number' => $document->document_number,
                'type' => $document->document_type->value,
            ],
            auditable: $document,
            actorType: 'system'
        );

        return $document;
    }

    /**
     * Generate an official invoice for a booking.
     */
    public function generateInvoice(Booking $booking): FinancialDocument
    {
        $booking->loadMissing(['currentProposal', 'paymentTransactions']);
        $now = Carbon::now('Asia/Colombo');
        $documentNumber = DocumentSequence::nextNumber(FinancialDocumentType::FinalInvoice, (int) $now->year);

        $checksum = hash('sha256', sprintf(
            '%s|%d|%s|%d|%s',
            $documentNumber,
            $booking->id,
            $booking->currency ?: 'USD',
            $booking->total_amount_minor_units ?? 0,
            $now->toIso8601String()
        ));

        $relativeFilePath = sprintf(
            'documents/%d/%s.html',
            $booking->id,
            $documentNumber
        );

        $html = view('documents.receipt', [
            'booking' => $booking,
            'proposal' => $booking->currentProposal,
            'transaction' => $booking->paymentTransactions->last(),
            'documentNumber' => $documentNumber,
            'issuedAt' => $now,
            'checksum' => $checksum,
            'isInvoice' => true,
        ])->render();

        Storage::disk('local')->put('private/'.$relativeFilePath, $html);

        /** @var FinancialDocument $document */
        $document = FinancialDocument::create([
            'booking_id' => $booking->id,
            'proposal_id' => $booking->current_proposal_id,
            'payment_transaction_id' => $booking->paymentTransactions->last()?->id,
            'document_type' => FinancialDocumentType::FinalInvoice,
            'document_number' => $documentNumber,
            'version' => 1,
            'amount_minor_units' => $booking->total_amount_minor_units ?? 0,
            'currency' => $booking->currency ?: 'USD',
            'file_path' => $relativeFilePath,
            'checksum' => $checksum,
            'metadata' => [
                'service_type' => $booking->service_type->value,
            ],
            'issued_at' => $now,
        ]);

        return $document;
    }
}
