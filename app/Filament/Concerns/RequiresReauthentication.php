<?php

namespace App\Filament\Concerns;

use App\Domain\Identity\Services\ReauthService;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

trait RequiresReauthentication
{
    public static function configureReauthentication(Action $action, string $reason = 'This sensitive action requires password confirmation.'): Action
    {
        return $action
            ->requiresConfirmation()
            ->modalHeading('Confirm Password')
            ->modalDescription($reason)
            ->form(function (): array {
                if (app(ReauthService::class)->isRecentlyConfirmed()) {
                    return [];
                }

                return [
                    TextInput::make('reauth_password')
                        ->label('Account Password')
                        ->password()
                        ->required()
                        ->currentPassword()
                        ->dehydrated(false),
                ];
            })
            ->before(function () use ($action): void {
                $reauth = app(ReauthService::class);
                $user = auth()->user();
                if (! $reauth->isRecentlyConfirmed() && $user instanceof User) {
                    $reauth->confirm($user, $action->getName());
                }
            });
    }
}
