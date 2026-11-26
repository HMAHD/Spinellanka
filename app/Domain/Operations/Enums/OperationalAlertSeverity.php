<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum OperationalAlertSeverity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::Warning => 'Warning',
            self::Info => 'Notice',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Critical => 'heroicon-o-exclamation-triangle',
            self::Warning => 'heroicon-o-exclamation-circle',
            self::Info => 'heroicon-o-information-circle',
        };
    }
}
