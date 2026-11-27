<?php

use App\Domain\Booking\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

it('redirects service selection request to the corresponding service page', function (ServiceType $service): void {
    $this->get(route('services.choose', ['service' => $service->value]))
        ->assertRedirect(route('services.show', ['service' => $service->value]));
})->with(ServiceType::cases());

it('rejects invalid or missing service choices with 404', function (): void {
    $this->get(route('services.choose', ['service' => 'invalid-choice']))
        ->assertNotFound();

    $this->get(route('services.choose'))
        ->assertNotFound();
});

it('contains no invented fleet facts or pricing promises on public pages', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Pricing, availability, and booking actions arrive only with verified fleet and provider data.')
        ->assertSee('No price or vehicle detail appears until verified data is available.')
        ->assertDontSee('Toyota')
        ->assertDontSee('Prius')
        ->assertDontSee('$');
});

it('ensures service pages show pending status and no mock pricing', function (ServiceType $service): void {
    $this->get(route('services.show', ['service' => $service->value]))
        ->assertOk()
        ->assertSee('Pending until staff confirmation')
        ->assertDontSee('Toyota')
        ->assertDontSee('Prius')
        ->assertDontSee('$');
})->with(ServiceType::cases());

it('ensures skip-link target exists with accessible focus on home', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="#page-title"', false)
        ->assertSee('id="page-title"', false);
});

it('ensures skip-link target exists with accessible focus on service pages', function (ServiceType $service): void {
    $this->get(route('services.show', ['service' => $service->value]))
        ->assertOk()
        ->assertSee('href="#page-title"', false)
        ->assertSee('id="page-title"', false);
})->with(ServiceType::cases());
