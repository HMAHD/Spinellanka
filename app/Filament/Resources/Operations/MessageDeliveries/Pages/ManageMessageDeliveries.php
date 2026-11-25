<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\MessageDeliveries\Pages;

use App\Filament\Resources\Operations\MessageDeliveries\MessageDeliveryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageMessageDeliveries extends ManageRecords
{
    protected static string $resource = MessageDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
