<?php

declare(strict_types=1);

namespace App\Filament\Resources\Offers\Promotions\Pages;

use App\Filament\Resources\Offers\Promotions\PromotionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePromotions extends ManageRecords
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
