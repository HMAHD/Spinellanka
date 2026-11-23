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
    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('Pricing, availability, and booking actions arrive only with verified fleet and provider data.')
        ->assertSee('No price or vehicle detail appears until verified data is available.')
        ->assertDontSee('Toyota')
        ->assertDontSee('Prius')
        ->assertDontSee('$')
        ->assertDontSee('USD')
        ->assertDontSee('LKR');

    foreach (ServiceType::cases() as $service) {
        $this->get(route('services.show', ['service' => $service->value]))
            ->assertOk()
            ->assertSee('Pending until staff confirmation')
            ->assertDontSee('Toyota')
            ->assertDontSee('$')
            ->assertDontSee('USD')
            ->assertDontSee('LKR');
    }
});

it('ensures skip-link target exists with accessible focus on all public pages', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="#page-title"', false)
        ->assertSee('id="page-title"', false);

    foreach (ServiceType::cases() as $service) {
        $this->get(route('services.show', ['service' => $service->value]))
            ->assertOk()
            ->assertSee('href="#page-title"', false)
            ->assertSee('id="page-title"', false);
    }
});
