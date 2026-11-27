<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Messaging\Adapters\FakeWhatsAppSender;
use App\Domain\Messaging\Adapters\MetaWhatsAppSender;
use App\Domain\Messaging\Contracts\WhatsAppSender;
use App\Domain\Messaging\DTOs\WhatsAppMessage;
use App\Domain\Messaging\Enums\DeliveryResultStatus;
use App\Domain\Operations\Services\ProviderReadinessService;
use App\Models\Booking;
use App\Models\GuestAccessToken;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('reports non-secret provider readiness and runs backup:verify cleanly', function (): void {
    Config::set('services.maps.key', 'SECRET_SHOULD_NEVER_PRINT');
    Config::set('services.meta_whatsapp.system_user_token', 'SECRET_WA_TOKEN');

    $exit = Artisan::call('providers:readiness');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Maps & Routes')
        ->and($output)->toContain('WhatsApp Business')
        ->and($output)->toContain('Off-Host Backups')
        ->and($output)->not->toContain('SECRET_SHOULD_NEVER_PRINT')
        ->and($output)->not->toContain('SECRET_WA_TOKEN');

    $readiness = app(ProviderReadinessService::class)->evaluateAll();
    expect($readiness)->toHaveCount(7);

    $backupDir = storage_path('framework/testing/backup_verify_'.Str::random(6));
    try {
        $backupExit = Artisan::call('backup:verify', [
            '--output-dir' => $backupDir,
            '--keep-manifest' => true,
        ]);
        $backupOutput = Artisan::output();
        expect($backupExit)->toBe(0)
            ->and($backupOutput)->toContain('Local backup capability verified')
            ->and($backupOutput)->toContain('BLOCKED');

        $manifests = File::glob("{$backupDir}/*.sha256");
        expect($manifests)->toHaveCount(1);
        $decoded = json_decode((string) File::get($manifests[0]), true);
        expect($decoded['off_host_backup_status'])->toBe('blocked_unprovisioned');
    } finally {
        File::deleteDirectory($backupDir);
    }
});

it('renders consent-aware Plausible analytics only on allowlisted public routes and never on private, booking, driver, or 404 routes', function (): void {
    // Disabled when config is absent
    Config::set('services.analytics.provider', 'none');
    Config::set('services.analytics.plausible_domain', null);
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('analytics-consent-bar')
        ->assertDontSee('Analytics settings');

    // Enabled on allowlisted public routes when configured
    Config::set('services.analytics.provider', 'plausible');
    Config::set('services.analytics.plausible_domain', 'spinellanka.example');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('id="analytics-consent-bar"', false)
        ->assertSee('data-analytics-domain="spinellanka.example"', false)
        ->assertSee('data-analytics-manage', false);

    $this->get(route('how-it-works'))->assertOk()->assertSee('id="analytics-consent-bar"', false);
    $this->get(route('fleet.index'))->assertOk()->assertSee('id="analytics-consent-bar"', false);

    // Strictly excluded from private guest booking status, invalid/404 token URLs, driver routes, and admin
    $vehicle = Vehicle::factory()->create();
    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'service_type' => ServiceType::AirportPickup,
        'status' => BookingStatus::Submitted,
    ]);
    $rawToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($rawToken),
        'purpose' => 'guest_status',
        'expires_at' => now()->addDays(7),
    ]);

    $this->get(route('booking.status', ['reference' => $booking->reference, 'token' => $rawToken]))
        ->assertOk()
        ->assertDontSee('analytics-consent-bar')
        ->assertDontSee('plausible.io');

    // 404 route must NOT load analytics
    $this->get('/booking/UNKNOWN-REF/secret-token-leak-test')
        ->assertNotFound()
        ->assertDontSee('analytics-consent-bar')
        ->assertDontSee('plausible.io');

    // Driver trip invalid route must NOT load analytics
    $this->get('/driver/trip/invalid-driver-token')
        ->assertNotFound()
        ->assertDontSee('analytics-consent-bar');
});

it('hides synthetic staging vehicles on public fleet routes if environment is production', function (): void {
    $synth = Vehicle::factory()->create([
        'internal_fleet_id' => 'SYNTH-VEH-99',
        'public_name' => 'SYNTHETIC Hidden In Production',
        'status' => 'active',
    ]);

    $this->get(route('fleet.index'))->assertOk()->assertSee('SYNTHETIC Hidden In Production');

    app()->detectEnvironment(fn (): string => 'production');
    try {
        $this->get(route('fleet.index'))->assertOk()->assertDontSee('SYNTHETIC Hidden In Production');
        $this->get(route('fleet.show', $synth))->assertNotFound();
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

it('registers exception context scrubber without exposing booking or driver tokens', function (): void {
    $user = User::factory()->create();
    expect($user)->not->toBeNull();
    $response = $this->get('/booking/BKG-123/secret-raw-token-abc');
    $response->assertNotFound();
});

it('keeps WhatsAppSender on fake adapter when Meta token is missing and rejects unapproved Meta templates', function (): void {
    Config::set('services.whatsapp.provider', 'meta_whatsapp');
    Config::set('services.meta_whatsapp.phone_number_id', '123456789');
    Config::set('services.meta_whatsapp.system_user_token', null);

    expect(app(WhatsAppSender::class))
        ->toBeInstanceOf(FakeWhatsAppSender::class);

    $sender = new MetaWhatsAppSender('123456789', 'EAAB_test_token');
    $result = $sender->send(new WhatsAppMessage(
        recipientPhone: '+94771234567',
        templateName: 'unapproved_marketing_blast',
        templateParams: [],
    ));

    expect($result->status)->toBe(DeliveryResultStatus::Failed)
        ->and($result->errorCode)->toBe('UNAPPROVED_TEMPLATE')
        ->and($result->isRetryable)->toBeFalse();
});
