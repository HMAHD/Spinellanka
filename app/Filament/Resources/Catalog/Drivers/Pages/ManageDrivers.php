<?php

namespace App\Filament\Resources\Catalog\Drivers\Pages;

use App\Domain\Catalog\Services\DriverService;
use App\Filament\Resources\Catalog\Drivers\DriverResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDrivers extends ManageRecords
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data) => app(DriverService::class)->createDriver($data, auth()->user())),
        ];
    }
}
