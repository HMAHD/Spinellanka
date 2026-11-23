<?php

namespace App\Filament\Resources\Catalog\Drivers\Pages;

use App\Domain\Audit\Services\AuditService;
use App\Filament\Resources\Catalog\Drivers\DriverResource;
use App\Models\Driver;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDrivers extends ManageRecords
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->after(function (Driver $record) {
                    app(AuditService::class)->record(
                        action: 'fleet.driver.created',
                        actor: auth()->user(),
                        summary: "Created driver record {$record->public_name}",
                        metadata: ['driver_id' => $record->id, 'public_name' => $record->public_name],
                        auditable: $record
                    );
                }),
        ];
    }
}

