<?php

namespace App\Filament\Resources\Catalog\Extras\Pages;

use App\Domain\Audit\Services\AuditService;
use App\Filament\Resources\Catalog\Extras\ExtraResource;
use App\Models\Extra;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExtras extends ManageRecords
{
    protected static string $resource = ExtraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->after(function (Extra $record) {
                    app(AuditService::class)->record(
                        action: 'fleet.extra.created',
                        actor: auth()->user(),
                        summary: "Created catalog extra {$record->name}",
                        metadata: ['extra_id' => $record->id, 'name' => $record->name, 'price' => $record->price_minor_units],
                        auditable: $record
                    );
                }),
        ];
    }
}
