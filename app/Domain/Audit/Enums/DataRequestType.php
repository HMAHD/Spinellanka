<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

enum DataRequestType: string
{
    case AccessExport = 'access_export';
    case Correction = 'correction';
    case ErasureDeletion = 'erasure_deletion';
    case Restriction = 'restriction';

    public function label(): string
    {
        return match ($this) {
            self::AccessExport => 'Right of Access / Data Export',
            self::Correction => 'Right to Rectification / Correction',
            self::ErasureDeletion => 'Right to Erasure / Deletion',
            self::Restriction => 'Right to Restriction of Processing',
        };
    }
}
