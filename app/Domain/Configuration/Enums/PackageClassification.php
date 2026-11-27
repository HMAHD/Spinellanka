<?php

declare(strict_types=1);

namespace App\Domain\Configuration\Enums;

enum PackageClassification: string
{
    case ProductionApproved = 'production_approved';
    case StagingSynthetic = 'staging_synthetic';

    public function label(): string
    {
        return match ($this) {
            self::ProductionApproved => 'Production Approved',
            self::StagingSynthetic => 'Staging Synthetic (CHG-003)',
        };
    }
}
