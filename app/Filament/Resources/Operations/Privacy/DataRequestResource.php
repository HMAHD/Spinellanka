<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Privacy;

use App\Domain\Audit\Enums\DataRequestStatus;
use App\Domain\Audit\Enums\DataRequestType;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Permission;
use App\Filament\Resources\Operations\Privacy\Pages\ManageDataRequests;
use App\Models\DataRequest;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DataRequestResource extends Resource
{
    protected static ?string $model = DataRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Privacy & Compliance';

    protected static ?string $navigationLabel = 'DSAR Requests';

    protected static ?int $navigationSort = 2;

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Metadata')
                    ->components([
                        TextInput::make('request_reference')
                            ->label('Reference')
                            ->disabled()
                            ->dehydrated(false),

                        Select::make('request_type')
                            ->label('Request Type')
                            ->options([
                                DataRequestType::AccessExport->value => DataRequestType::AccessExport->label(),
                                DataRequestType::Correction->value => DataRequestType::Correction->label(),
                                DataRequestType::ErasureDeletion->value => DataRequestType::ErasureDeletion->label(),
                                DataRequestType::Restriction->value => DataRequestType::Restriction->label(),
                            ])
                            ->required(),

                        TextInput::make('subject_email')
                            ->label('Subject Email')
                            ->email()
                            ->required(),

                        TextInput::make('subject_name')
                            ->label('Subject Name'),

                        TextInput::make('subject_phone')
                            ->label('Subject Phone'),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                DataRequestStatus::PendingVerification->value => DataRequestStatus::PendingVerification->label(),
                                DataRequestStatus::Verified->value => DataRequestStatus::Verified->label(),
                                DataRequestStatus::InProgress->value => DataRequestStatus::InProgress->label(),
                                DataRequestStatus::Completed->value => DataRequestStatus::Completed->label(),
                                DataRequestStatus::Rejected->value => DataRequestStatus::Rejected->label(),
                            ])
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('verified_at')
                            ->label('Verified At')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('completed_at')
                            ->label('Completed At')
                            ->disabled()
                            ->dehydrated(false),

                        Textarea::make('notes')
                            ->label('Compliance Notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('request_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (DataRequestType $state): string => match ($state) {
                        DataRequestType::AccessExport => 'info',
                        DataRequestType::Correction => 'warning',
                        DataRequestType::ErasureDeletion => 'danger',
                        DataRequestType::Restriction => 'gray',
                    })
                    ->formatStateUsing(fn (DataRequestType $state): string => $state->label()),

                TextColumn::make('subject_email')
                    ->label('Subject Email')
                    ->searchable(),

                TextColumn::make('subject_name')
                    ->label('Subject Name')
                    ->searchable()
                    ->placeholder('N/A'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (DataRequestStatus $state): string => match ($state) {
                        DataRequestStatus::PendingVerification => 'gray',
                        DataRequestStatus::Verified => 'info',
                        DataRequestStatus::InProgress => 'warning',
                        DataRequestStatus::Completed => 'success',
                        DataRequestStatus::Rejected => 'danger',
                    })
                    ->formatStateUsing(fn (DataRequestStatus $state): string => $state->label()),

                TextColumn::make('verified_at')
                    ->label('Verified At')
                    ->dateTime()
                    ->placeholder('Pending'),

                TextColumn::make('completed_at')
                    ->label('Completed At')
                    ->dateTime()
                    ->placeholder('In Progress'),

                TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        DataRequestStatus::PendingVerification->value => DataRequestStatus::PendingVerification->label(),
                        DataRequestStatus::Verified->value => DataRequestStatus::Verified->label(),
                        DataRequestStatus::InProgress->value => DataRequestStatus::InProgress->label(),
                        DataRequestStatus::Completed->value => DataRequestStatus::Completed->label(),
                        DataRequestStatus::Rejected->value => DataRequestStatus::Rejected->label(),
                    ]),

                SelectFilter::make('request_type')
                    ->options([
                        DataRequestType::AccessExport->value => DataRequestType::AccessExport->label(),
                        DataRequestType::Correction->value => DataRequestType::Correction->label(),
                        DataRequestType::ErasureDeletion->value => DataRequestType::ErasureDeletion->label(),
                        DataRequestType::Restriction->value => DataRequestType::Restriction->label(),
                    ]),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('mark_verified')
                    ->label('Verify Request')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (DataRequest $record): bool => $record->status === DataRequestStatus::PendingVerification)
                    ->action(function (DataRequest $record, AuditService $auditService): void {
                        $user = auth()->user();
                        $record->update([
                            'status' => DataRequestStatus::Verified,
                            'verified_at' => Carbon::now('UTC'),
                        ]);

                        $auditService->record(
                            action: 'dsar.verified',
                            actor: $user,
                            summary: "Data request {$record->request_reference} marked as verified by {$user?->name}",
                            metadata: [
                                'request_reference' => $record->request_reference,
                                'subject_email' => $record->subject_email,
                            ],
                            auditable: $record
                        );

                        Notification::make()
                            ->title('Request Verified')
                            ->success()
                            ->send();
                    }),

                Action::make('complete')
                    ->label('Complete Request')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('success')
                    ->visible(fn (DataRequest $record): bool => in_array($record->status, [DataRequestStatus::Verified, DataRequestStatus::InProgress], true))
                    ->form([
                        Textarea::make('resolution_notes')
                            ->label('Resolution Details & Proof')
                            ->required()
                            ->helperText('State the actions taken (e.g. data export generated and sent, or anonymization verified).'),
                    ])
                    ->action(function (DataRequest $record, array $data, AuditService $auditService): void {
                        $user = auth()->user();
                        $existingNotes = $record->notes ? $record->notes."\n\n" : '';
                        $newNotes = $existingNotes.sprintf("[%s] Completed by %s:\n%s", Carbon::now('Asia/Colombo')->toDateTimeString(), $user?->name, $data['resolution_notes']);

                        $record->update([
                            'status' => DataRequestStatus::Completed,
                            'completed_at' => Carbon::now('UTC'),
                            'processed_by_user_id' => $user?->id,
                            'notes' => $newNotes,
                        ]);

                        $auditService->record(
                            action: 'dsar.completed',
                            actor: $user,
                            summary: "Data request {$record->request_reference} marked completed by {$user?->name}",
                            metadata: [
                                'request_reference' => $record->request_reference,
                                'subject_email' => $record->subject_email,
                                'notes' => $data['resolution_notes'],
                            ],
                            auditable: $record
                        );

                        Notification::make()
                            ->title('Data Request Completed')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject Request')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (DataRequest $record): bool => ! in_array($record->status, [DataRequestStatus::Completed, DataRequestStatus::Rejected], true))
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->helperText('State the statutory or verification basis for rejection.'),
                    ])
                    ->action(function (DataRequest $record, array $data, AuditService $auditService): void {
                        $user = auth()->user();
                        $existingNotes = $record->notes ? $record->notes."\n\n" : '';
                        $newNotes = $existingNotes.sprintf("[%s] Rejected by %s:\n%s", Carbon::now('Asia/Colombo')->toDateTimeString(), $user?->name, $data['rejection_reason']);

                        $record->update([
                            'status' => DataRequestStatus::Rejected,
                            'completed_at' => Carbon::now('UTC'),
                            'processed_by_user_id' => $user?->id,
                            'notes' => $newNotes,
                        ]);

                        $auditService->record(
                            action: 'dsar.rejected',
                            actor: $user,
                            summary: "Data request {$record->request_reference} rejected by {$user?->name}: {$data['rejection_reason']}",
                            metadata: [
                                'request_reference' => $record->request_reference,
                                'subject_email' => $record->subject_email,
                                'reason' => $data['rejection_reason'],
                            ],
                            auditable: $record
                        );

                        Notification::make()
                            ->title('Data Request Rejected')
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDataRequests::route('/'),
        ];
    }
}
