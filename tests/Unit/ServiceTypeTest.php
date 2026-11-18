<?php

use App\Domain\Booking\ServiceType;

it('distinguishes chauffeured and self-drive services', function (): void {
    expect(ServiceType::AirportPickup->includesDriver())->toBeTrue()
        ->and(ServiceType::AirportTransfer->includesDriver())->toBeTrue()
        ->and(ServiceType::Ride->includesDriver())->toBeTrue()
        ->and(ServiceType::Rent->includesDriver())->toBeFalse();
});
