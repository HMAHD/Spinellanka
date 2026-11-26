<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Payment\Adapters\FakeHostedPaymentGateway;
use App\Domain\Payment\Adapters\FakePaymentWebhookVerifier;
use App\Domain\Payment\Adapters\PayHereHostedPaymentGateway;
use App\Domain\Payment\Adapters\PayHereWebhookVerifier;
use App\Domain\Payment\Contracts\HostedPaymentGatewayInterface;
use App\Domain\Payment\Contracts\PaymentWebhookVerifierInterface;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register payment services and gateway contracts.
     */
    public function register(): void
    {
        $this->app->singleton(FakeHostedPaymentGateway::class, fn () => new FakeHostedPaymentGateway);
        $this->app->singleton(FakePaymentWebhookVerifier::class, fn () => new FakePaymentWebhookVerifier);
        $this->app->singleton(PayHereHostedPaymentGateway::class, fn () => new PayHereHostedPaymentGateway);
        $this->app->singleton(PayHereWebhookVerifier::class, fn () => new PayHereWebhookVerifier);

        $this->app->bind(HostedPaymentGatewayInterface::class, function ($app) {
            $gateway = config('services.payment.gateway', 'fake');

            return match ($gateway) {
                'payhere' => $app->make(PayHereHostedPaymentGateway::class),
                default => $app->make(FakeHostedPaymentGateway::class),
            };
        });

        $this->app->bind(PaymentWebhookVerifierInterface::class, function ($app) {
            $gateway = config('services.payment.gateway', 'fake');

            return match ($gateway) {
                'payhere' => $app->make(PayHereWebhookVerifier::class),
                default => $app->make(FakePaymentWebhookVerifier::class),
            };
        });
    }
}
