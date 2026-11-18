<?php

use App\Domain\Booking\ServiceType;

it('renders the public home page', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Transport planned ahead')
        ->assertSee('Airport Pickup')
        ->assertSee('Rent');
});

it('opens every approved service page', function (ServiceType $service): void {
    $this->get(route('services.show', ['service' => $service->value]))
        ->assertOk()
        ->assertSee($service->label())
        ->assertSee($service->summary());
})->with(ServiceType::cases());

it('rejects unsupported service names', function (): void {
    $this->get('/services/not-a-service')->assertNotFound();
});
