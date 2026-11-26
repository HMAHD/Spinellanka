<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum FinancialDocumentType: string
{
    case Estimate = 'estimate';
    case Proposal = 'proposal';
    case Receipt = 'receipt';
    case FinalInvoice = 'final_invoice';
    case RentalAgreement = 'rental_agreement';

    public function label(): string
    {
        return match ($this) {
            self::Estimate => 'Price Estimate',
            self::Proposal => 'Official Proposal',
            self::Receipt => 'Payment Receipt',
            self::FinalInvoice => 'Final Tax Invoice',
            self::RentalAgreement => 'Self-Drive Rental Agreement',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Estimate => 'EST',
            self::Proposal => 'PROP',
            self::Receipt => 'REC',
            self::FinalInvoice => 'INV',
            self::RentalAgreement => 'AGR',
        };
    }
}
