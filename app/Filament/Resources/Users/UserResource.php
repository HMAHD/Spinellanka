<?php

namespace App\Filament\Resources\Users;

use App\Domain\Identity\Services\ReauthService;
use App\Domain\Identity\Services\SessionRevocationService;
use App\Domain\Identity\Services\UserService;
use App\Filament\Concerns\RequiresReauthentication;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    use RequiresReauthentication;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static UnitEnum|string|null $navigationGroup = 'Staff Administration';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(128),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(191),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(12)
                    ->helperText('Minimum 12 characters. Leave blank when editing to keep existing password.'),
                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->preload()
                    ->required()
                    ->helperText('Select assigned role for this staff member.'),
                Toggle::make('is_active')
                    ->label('Active Staff Account')
                    ->default(true)
                    ->helperText('Inactive staff accounts are immediately blocked from logging in.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->label('Role'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                IconColumn::make('has_mfa')
                    ->label('MFA Enrolled')
                    ->state(fn (User $record): bool => filled($record->app_authentication_secret))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        unset($data['password']);

                        return $data;
                    }),
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->visible(fn (User $record): bool => $record->is_active && (auth()->user()?->can('deactivate', $record) ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Deactivate Staff Account')
                    ->modalDescription(fn (User $record): string => "Are you sure you want to deactivate {$record->email}? All active sessions will be revoked immediately.")
                    ->form(function (): array {
                        if (app(ReauthService::class)->isRecentlyConfirmed()) {
                            return [];
                        }

                        return [
                            TextInput::make('reauth_password')
                                ->label('Your Password (Re-authentication)')
                                ->password()
                                ->required()
                                ->currentPassword()
                                ->dehydrated(false),
                        ];
                    })
                    ->action(function (User $record, UserService $userService, ReauthService $reauthService): void {
                        $reauthService->confirm(auth()->user(), 'user.deactivate');
                        // Consume immediately for tier-1 action
                        $reauthService->consumeConfirmation();

                        $userService->deactivate(auth()->user(), $record);

                        Notification::make()
                            ->title('Staff Account Deactivated')
                            ->body("{$record->email} has been deactivated and all active sessions revoked.")
                            ->success()
                            ->send();
                    }),
                Action::make('activate')
                    ->label('Activate')
                    ->color('success')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (User $record): bool => ! $record->is_active && (auth()->user()?->can('update', $record) ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Activate Staff Account')
                    ->action(function (User $record, UserService $userService): void {
                        $userService->activate(auth()->user(), $record);

                        Notification::make()
                            ->title('Staff Account Activated')
                            ->body("{$record->email} has been activated.")
                            ->success()
                            ->send();
                    }),
                Action::make('revoke_sessions')
                    ->label('Revoke Sessions')
                    ->color('warning')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (User $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Revoke Active Sessions')
                    ->modalDescription(fn (User $record): string => "Revoke all active browser sessions for {$record->email} across all devices.")
                    ->form(function (): array {
                        if (app(ReauthService::class)->isRecentlyConfirmed()) {
                            return [];
                        }

                        return [
                            TextInput::make('reauth_password')
                                ->label('Your Password (Re-authentication)')
                                ->password()
                                ->required()
                                ->currentPassword()
                                ->dehydrated(false),
                        ];
                    })
                    ->action(function (User $record, SessionRevocationService $sessionService, ReauthService $reauthService): void {
                        $reauthService->confirm(auth()->user(), 'session.revoke');
                        $count = $sessionService->revokeUserSessions($record);

                        Notification::make()
                            ->title('Sessions Revoked')
                            ->body("Revoked {$count} active session(s) for {$record->email}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
