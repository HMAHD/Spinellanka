<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Configuration\Services\ConfigurationTransferService;
use App\Domain\Identity\Permission;
use App\Domain\Identity\Services\ReauthService;
use App\Domain\Operations\Services\ProviderReadinessService;
use App\Models\ConfigurationSnapshot;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

class ConfigurationManagement extends Page
{
    protected string $view = 'filament.pages.configuration-management';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Configuration & Providers';

    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::ConfigManage->value);
    }

    protected function getHeaderActions(): array
    {
        $snapshots = ConfigurationSnapshot::query()
            ->where('is_active', false)
            ->orderByDesc('version_number')
            ->get()
            ->mapWithKeys(fn (ConfigurationSnapshot $s): array => [
                $s->id => "v{$s->version_number} ({$s->package_classification->label()}) — ".substr($s->checksum_sha256, 0, 12),
            ])
            ->all();

        return [
            Action::make('rollbackConfiguration')
                ->label('Roll Back Configuration')
                ->color('warning')
                ->disabled($snapshots === [])
                ->requiresConfirmation()
                ->modalHeading('Confirm Configuration Rollback')
                ->modalDescription('Confirm your password to roll back the active business configuration.')
                ->form(function () use ($snapshots): array {
                    $fields = [
                        Select::make('snapshot_id')
                            ->label('Target Snapshot Version')
                            ->options($snapshots)
                            ->required(),
                        TextInput::make('reason')
                            ->label('Rollback Reason')
                            ->required()
                            ->maxLength(255)
                            ->default('Restoring prior verified snapshot'),
                    ];

                    if (! app(ReauthService::class)->isRecentlyConfirmed()) {
                        $fields[] = TextInput::make('reauth_password')
                            ->label('Account Password (Re-authentication)')
                            ->password()
                            ->required()
                            ->currentPassword()
                            ->dehydrated(false);
                    }

                    return $fields;
                })
                ->action(function (array $data, ConfigurationTransferService $transferService, ReauthService $reauthService): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User || ! $actor->can(Permission::ConfigManage->value)) {
                        abort(403);
                    }

                    if (! $reauthService->isRecentlyConfirmed()) {
                        $reauthService->confirm($actor, 'configuration.rollback');
                    }
                    $reauthService->consumeConfirmation();

                    try {
                        $result = $transferService->rollback(
                            targetSnapshotId: isset($data['snapshot_id']) ? (int) $data['snapshot_id'] : null,
                            actor: $actor,
                            reason: (string) ($data['reason'] ?? 'Filament admin rollback'),
                        );

                        $snapshot = $result->snapshot;
                        $version = $snapshot !== null ? (string) $snapshot->version_number : '?';

                        Notification::make()
                            ->title("Rolled back to snapshot v{$version}")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Rollback failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'activeSnapshot' => ConfigurationSnapshot::query()->where('is_active', true)->first(),
            'snapshots' => ConfigurationSnapshot::query()->orderByDesc('version_number')->limit(15)->get(),
            'providers' => app(ProviderReadinessService::class)->evaluateAll(),
            'hasSyntheticState' => app(ProviderReadinessService::class)->hasSyntheticStagingState(),
        ];
    }
}
