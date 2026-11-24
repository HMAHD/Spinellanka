<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\PricingRules\Pages;

use App\Domain\Pricing\Services\PricingRuleService;
use App\Filament\Resources\Operations\PricingRules\PricingRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePricingRules extends ManageRecords
{
    protected static string $resource = PricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data) => app(PricingRuleService::class)->createRule($data, auth()->user())),
        ];
    }
}
