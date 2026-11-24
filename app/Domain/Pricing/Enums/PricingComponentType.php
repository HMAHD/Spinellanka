<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Enums;

enum PricingComponentType: string
{
    case Base = 'base';
    case Distance = 'distance';
    case Duration = 'duration';
    case Stop = 'stop';
    case Waiting = 'waiting';
    case AirportFee = 'airport_fee';
    case Toll = 'toll';
    case Delivery = 'delivery';
    case Insurance = 'insurance';
    case Deposit = 'deposit';
    case Tax = 'tax';
    case Discount = 'discount';
    case Extra = 'extra';

    public function label(): string
    {
        return match ($this) {
            self::Base => 'Base Charge',
            self::Distance => 'Distance Rate',
            self::Duration => 'Duration Rate',
            self::Stop => 'Additional Stop Fee',
            self::Waiting => 'Waiting Time Fee',
            self::AirportFee => 'Airport Access / Parking Fee',
            self::Toll => 'Expressway Toll Fee',
            self::Delivery => 'Vehicle Delivery / Collection Fee',
            self::Insurance => 'Insurance Coverage',
            self::Deposit => 'Refundable Security Deposit',
            self::Tax => 'Government Tax / Levy',
            self::Discount => 'Promotional / Scope Discount',
            self::Extra => 'Equipment / Extra Item',
        };
    }
}
