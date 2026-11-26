<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Payments\Pages;

use App\Filament\Resources\Operations\Payments\PaymentTransactionResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentTransactions extends ManageRecords
{
    protected static string $resource = PaymentTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
