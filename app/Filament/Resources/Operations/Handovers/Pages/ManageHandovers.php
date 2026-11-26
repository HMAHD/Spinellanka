<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Handovers\Pages;

use App\Filament\Resources\Operations\Handovers\HandoverResource;
use Filament\Resources\Pages\ManageRecords;

class ManageHandovers extends ManageRecords
{
    protected static string $resource = HandoverResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
