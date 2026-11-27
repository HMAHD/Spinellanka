<?php

namespace App\Providers;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Role;
use App\Domain\Location\Contracts\PlaceSearchContract;
use App\Domain\Location\Contracts\RouteEstimateContract;
use App\Domain\Location\Services\GoogleMapsPlaceSearchService;
use App\Domain\Location\Services\GoogleMapsRouteEstimateService;
use App\Domain\Location\Services\NullPlaceSearchService;
use App\Domain\Location\Services\NullRouteEstimateService;
use App\Domain\Messaging\Adapters\FakeTransactionalMailer;
use App\Domain\Messaging\Adapters\FakeWhatsAppSender;
use App\Domain\Messaging\Adapters\MetaWhatsAppSender;
use App\Domain\Messaging\Adapters\PostmarkTransactionalMailer;
use App\Domain\Messaging\Contracts\TransactionalMailer;
use App\Domain\Messaging\Contracts\WhatsAppSender;
use App\Models\AuditEvent;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NullPlaceSearchService::class);
        $this->app->singleton(NullRouteEstimateService::class);

        $this->app->bind(PlaceSearchContract::class, function ($app) {
            if (config('services.maps.provider') === 'google_maps' && filled(config('services.maps.key'))) {
                return $app->make(GoogleMapsPlaceSearchService::class);
            }

            return $app->make(NullPlaceSearchService::class);
        });

        $this->app->bind(RouteEstimateContract::class, function ($app) {
            if (config('services.maps.provider') === 'google_maps' && filled(config('services.maps.key'))) {
                return $app->make(GoogleMapsRouteEstimateService::class);
            }

            return $app->make(NullRouteEstimateService::class);
        });

        $this->app->singleton(WhatsAppSender::class, function () {
            $provider = config('services.whatsapp.provider', 'fake');
            if (
                $provider === 'meta_whatsapp'
                && filled(config('services.meta_whatsapp.phone_number_id'))
                && filled(config('services.meta_whatsapp.system_user_token'))
            ) {
                return new MetaWhatsAppSender;
            }

            return new FakeWhatsAppSender;
        });

        $this->app->singleton(TransactionalMailer::class, function () {
            $provider = config('services.transactional_mailer.provider', 'fake');
            if ($provider === 'postmark' && config('services.postmark.server_token')) {
                return new PostmarkTransactionalMailer;
            }

            return new FakeTransactionalMailer;
        });
    }

    public function boot(): void
    {
        if (request()->header('x-forwarded-proto') === 'https' || request()->server('HTTP_X_FORWARDED_PROTO') === 'https') {
            URL::forceScheme('https');
        }

        Model::shouldBeStrict(! $this->app->isProduction());

        // Bounded super-admin authorization: explicitly defers for AuditEvent and self/last-owner safety
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            $target = $arguments[0] ?? null;

            if ($target === AuditEvent::class || $target instanceof AuditEvent) {
                return null;
            }

            if (($target instanceof User || $target === User::class) && in_array($ability, ['delete', 'deactivate'], true)) {
                return null;
            }

            if (($target instanceof Vehicle || $target === Vehicle::class || $target instanceof Driver || $target === Driver::class) && $ability === 'delete') {
                return null;
            }

            return $user->hasRole(Role::OwnerAdmin->value) ? true : null;
        });

        // Audit authentication lifecycle events
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(AuditService::class)->record(
                    action: 'auth.login',
                    actor: $event->user,
                    summary: "Staff member {$event->user->email} logged in",
                    metadata: ['guard' => $event->guard],
                    auditable: $event->user
                );
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $attemptedEmail = $event->credentials['email'] ?? null;
            app(AuditService::class)->record(
                action: 'auth.failed',
                actor: $event->user instanceof User ? $event->user : null,
                summary: 'Failed login attempt',
                metadata: [
                    'guard' => $event->guard,
                    'attempted_email' => $attemptedEmail,
                ],
                auditable: $event->user instanceof User ? $event->user : null
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                app(AuditService::class)->record(
                    action: 'auth.logout',
                    actor: $event->user,
                    summary: "Staff member {$event->user->email} logged out",
                    metadata: ['guard' => $event->guard],
                    auditable: $event->user
                );
            }
        });
    }
}
