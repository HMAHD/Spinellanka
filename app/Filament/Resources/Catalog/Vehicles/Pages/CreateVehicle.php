<?php

namespace App\Filament\Resources\Catalog\Vehicles\Pages;

use App\Domain\Catalog\Services\VehicleService;
use App\Filament\Resources\Catalog\Vehicles\VehicleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(VehicleService::class)->createVehicle($data, auth()->user());
    }
}
