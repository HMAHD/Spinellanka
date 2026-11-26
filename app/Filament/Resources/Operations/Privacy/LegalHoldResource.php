<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Privacy;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Permission;
use App\Filament\Resources\Operations\Bookings\BookingResource;
use App\Filament\Resources\Operations\Privacy\Pages\ManageLegalHolds;
use App\Models\LegalHold;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LegalHoldResource extends Resource
{
    protected static ?string $model = LegalHold::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static UnitEnum|string|null $navigationGroup = 'Privacy & Compliance';

    protected static ?string $navigationLabel = 'Legal Holds';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::AuditView->value) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can(Permission::AuditView->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::AuditView->value) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function schema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Hold Details')->schema([
                TextInput::make('booking_reference')
                    ->label('Linked Booking')
                    ->formatStateUsing(fn ($record) => $record->booking?->reference)
                    ->disabled(),
                TextInput::make('reason')
                    ->label('Statutory / Litigation Reason')
                    ->disabled(),
                TextInput::make('applied_by')
                    ->label('Applied By')
                    ->formatStateUsing(fn ($record) => $record->appliedByUser?->name)
                    ->disabled(),
                TextInput::make('applied_at')
                    ->label('Applied At (Colombo)')
                    ->formatStateUsing(fn ($record) => $record->applied_at?->setTimezone('Asia/Colombo')->format('Y-m-d H:i'))
                    ->disabled(),
                TextInput::make('is_active')
                    ->label('Hold Status')
                    ->formatStateUsing(fn ($record) => $record->is_active ? 'Active (Retention Blocked)' : 'Released')
                    ->disabled(),
                TextInput::make('released_by')
                    ->label('Released By')
                    ->formatStateUsing(fn ($record) => $record->releasedByUser ? $record->releasedByUser->name : 'N/A')
                    ->disabled(),
                Textarea::make('details')
                    ->label('Case / Dispute Details')
                    ->disabled()
                    ->columnSpanFull(),
                Textarea::make('release_reason')
                    ->label('Release Justification')
                    ->disabled()
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'booking',
                'appliedByUser',
                'releasedByUser',
            ]))
            ->columns([
                TextColumn::make('booking.reference')
                    ->label('Booking Ref')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->booking ? BookingResource::getUrl('view', ['record' => $record->booking]) : null),

                TextColumn::make('reason')
                    ->label('Preservation Reason')
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Active Hold')
                    ->boolean(),

                TextColumn::make('appliedByUser.name')
                    ->label('Applied By'),

                TextColumn::make('applied_at')
                    ->label('Applied (Colombo)')
                    ->dateTime('M j, Y H:i', 'Asia/Colombo')
                    ->sortable(),

                TextColumn::make('released_at')
                    ->label('Released (Colombo)')
                    ->dateTime('M j, Y H:i', 'Asia/Colombo')
                    ->placeholder('Active'),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Hold Status')
                    ->options([
                        '1' => 'Active Holds (Protected)',
                        '0' => 'Released Holds',
                    ]),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('releaseHold')
                    ->label('Release Hold')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LegalHold $record) => $record->is_active)
                    ->form([
                        Textarea::make('release_reason')
                            ->label('Legal / Compliance Release Justification')
                            ->required(),
                    ])
                    ->action(function (LegalHold $record, array $data, AuditService $auditService) {
                        $user = auth()->user();
                        $record->update([
                            'is_active' => false,
                            'released_by_user_id' => $user?->id,
                            'released_at' => Carbon::now('UTC'),
                            'release_reason' => (string) $data['release_reason'],
                        ]);

                        $auditService->record(
                            action: 'legal_hold.released',
                            actor: $user,
                            summary: "Legal hold on booking {$record->booking->reference} released by {$user?->name}: {$data['release_reason']}",
                            metadata: [
                                'legal_hold_id' => $record->id,
                                'booking_id' => $record->booking_id,
                                'release_reason' => $data['release_reason'],
                            ],
                            auditable: $record
                        );

                        Notification::make()
                            ->title('Legal Hold Released')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLegalHolds::route('/'),
        ];
    }
}
