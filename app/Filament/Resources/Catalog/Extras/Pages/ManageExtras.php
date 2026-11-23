<?php

namespace App\Filament\Resources\Catalog\Extras\Pages;

use App\Domain\Catalog\Services\ExtraService;
use App\Filament\Resources\Catalog\Extras\ExtraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExtras extends ManageRecords
{
    protected static string $resource = ExtraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data) => app(ExtraService::class)->createExtra($data, auth()->user())),
        ];
    }
}
