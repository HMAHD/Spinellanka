<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\ExchangeRateBases\Pages;

use App\Domain\Pricing\Services\ExchangeRateService;
use App\Filament\Resources\Operations\ExchangeRateBases\ExchangeRateBasisResource;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExchangeRateBases extends ManageRecords
{
    protected static string $resource = ExchangeRateBasisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data) => app(ExchangeRateService::class)->recordRate(
                    rate: (float) $data['rate'],
                    sourceNote: $data['source_note'],
                    effectiveAt: isset($data['effective_at']) ? Carbon::parse($data['effective_at']) : null,
                    actor: auth()->user()
                )),
        ];
    }
}
