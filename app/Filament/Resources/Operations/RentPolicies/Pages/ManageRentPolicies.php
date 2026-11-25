<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\RentPolicies\Pages;

use App\Filament\Resources\Operations\RentPolicies\RentPolicyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRentPolicies extends ManageRecords
{
    protected static string $resource = RentPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
