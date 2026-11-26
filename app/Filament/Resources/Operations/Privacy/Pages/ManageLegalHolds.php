<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Privacy\Pages;

use App\Filament\Resources\Operations\Privacy\LegalHoldResource;
use Filament\Resources\Pages\ManageRecords;

class ManageLegalHolds extends ManageRecords
{
    protected static string $resource = LegalHoldResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
