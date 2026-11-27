<?php

declare(strict_types=1);

namespace App\Filament\Resources\Offers\Deals\Pages;

use App\Filament\Resources\Offers\Deals\DealResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDeals extends ManageRecords
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
