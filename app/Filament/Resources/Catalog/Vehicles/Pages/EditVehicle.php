<?php

namespace App\Filament\Resources\Catalog\Vehicles\Pages;

use App\Domain\Catalog\Services\VehicleService;
use App\Filament\Resources\Catalog\Vehicles\VehicleResource;
use App\Models\Vehicle;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Vehicle) {
            throw new InvalidArgumentException('Expected instance of Vehicle');
        }

        return app(VehicleService::class)->updateVehicle($record, $data, auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
