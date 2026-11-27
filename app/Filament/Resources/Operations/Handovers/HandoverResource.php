<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Handovers;

use App\Domain\Identity\Permission;
use App\Domain\Operations\Enums\FuelLevel;
use App\Domain\Operations\Enums\HandoverStatus;
use App\Domain\Operations\Enums\HandoverType;
use App\Domain\Operations\Services\HandoverService;
use App\Filament\Resources\Operations\Bookings\BookingResource;
use App\Filament\Resources\Operations\Handovers\Pages\ManageHandovers;
use App\Models\Handover;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
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

class HandoverResource extends Resource
{
    protected static ?string $model = Handover::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Rent Handovers';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::BookingView->value) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can(Permission::BookingView->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
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
            Section::make('Handover Details')->schema([
                TextInput::make('booking_reference')
                    ->label('Booking Reference')
                    ->formatStateUsing(fn ($record) => $record->booking?->reference)
                    ->disabled(),
                TextInput::make('vehicle_plate')
                    ->label('Vehicle License Plate')
                    ->formatStateUsing(fn ($record) => $record->vehicle?->license_plate)
                    ->disabled(),
                TextInput::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn ($record) => $record->type?->label())
                    ->disabled(),
                TextInput::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($record) => $record->status?->label())
                    ->disabled(),
                TextInput::make('location_text')
                    ->label('Location')
                    ->disabled(),
                TextInput::make('scheduled_at')
                    ->label('Scheduled At (Colombo)')
                    ->formatStateUsing(fn ($record) => $record->scheduled_at?->setTimezone('Asia/Colombo')->format('Y-m-d H:i'))
                    ->disabled(),
                TextInput::make('completed_at')
                    ->label('Completed At (Colombo)')
                    ->formatStateUsing(fn ($record) => $record->completed_at?->setTimezone('Asia/Colombo')->format('Y-m-d H:i') ?? 'Pending')
                    ->disabled(),
            ])->columns(2),

            Section::make('Document Physical Inspection (Strictly No Uploads)')->schema([
                TextInput::make('driver_licence_country')
                    ->label('Driver Licence Country')
                    ->disabled(),
                TextInput::make('idp_or_aac_verified')
                    ->label('IDP / AAC Endorsement Verified')
                    ->formatStateUsing(fn ($record) => $record->idp_or_aac_verified ? 'Yes' : 'No')
                    ->disabled(),
                Textarea::make('document_inspection_notes')
                    ->label('Inspection Notes')
                    ->disabled()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Release Gate & Exceptions')->schema([
                TextInput::make('is_released')
                    ->label('Vehicle Dispatched / Released')
                    ->formatStateUsing(fn ($record) => $record->is_released ? 'Released' : 'Blocked / Pending')
                    ->disabled(),
                TextInput::make('has_exception')
                    ->label('Authorized Exception Applied')
                    ->formatStateUsing(fn ($record) => $record->has_exception ? 'Yes' : 'No')
                    ->disabled(),
                Textarea::make('exception_reason')
                    ->label('Exception Reason & Authorization')
                    ->formatStateUsing(fn ($record) => $record->has_exception ? sprintf('%s (Authorized by %s at %s)', $record->exception_reason, $record->exceptionAuthorizedBy?->name, $record->exception_authorized_at?->setTimezone('Asia/Colombo')->format('Y-m-d H:i')) : 'None')
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
                'rentDetail',
                'vehicle',
                'staffUser',
                'conditionRecord',
                'exceptionAuthorizedBy',
            ]))
            ->columns([
                TextColumn::make('booking.reference')
                    ->label('Booking Ref')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->booking ? BookingResource::getUrl('view', ['record' => $record->booking]) : null),

                TextColumn::make('vehicle.license_plate')
                    ->label('Vehicle')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (HandoverType $state) => match ($state) {
                        HandoverType::Collection => 'info',
                        HandoverType::Return => 'warning',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (HandoverStatus $state) => match ($state) {
                        HandoverStatus::Scheduled => 'gray',
                        HandoverStatus::DocumentsVerified, HandoverStatus::ConditionCaptured, HandoverStatus::SignaturesCaptured => 'warning',
                        HandoverStatus::Released, HandoverStatus::Completed => 'success',
                        HandoverStatus::ExceptionApproved => 'danger',
                        HandoverStatus::Cancelled => 'gray',
                    }),

                IconColumn::make('documents_inspected')
                    ->label('Docs')
                    ->boolean(),

                IconColumn::make('is_released')
                    ->label('Released')
                    ->boolean(),

                TextColumn::make('scheduled_at')
                    ->label('Scheduled (Colombo)')
                    ->dateTime('M j, Y H:i', 'Asia/Colombo')
                    ->sortable(),

                TextColumn::make('staffUser.name')
                    ->label('Staff Inspector')
                    ->placeholder('Unassigned'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'collection' => 'Collection',
                        'return' => 'Return',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'documents_verified' => 'Documents Verified',
                        'condition_captured' => 'Condition Captured',
                        'released' => 'Released',
                        'completed' => 'Completed',
                        'exception_approved' => 'Exception Approved',
                    ]),
            ])
            ->actions([
                ViewAction::make(),

                // 1. Verify Documents Action (Collection Only)
                Action::make('verifyDocuments')
                    ->label('Verify Docs')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->color('info')
                    ->visible(fn (Handover $record) => $record->type === HandoverType::Collection && ! $record->documents_inspected && ! $record->status->isTerminal())
                    ->form([
                        Select::make('driver_licence_country')
                            ->label('Driver Licence Country of Issue')
                            ->options([
                                'LK' => 'Sri Lanka (LK)',
                                'GB' => 'United Kingdom (GB)',
                                'US' => 'United States (US)',
                                'DE' => 'Germany (DE)',
                                'FR' => 'France (FR)',
                                'AU' => 'Australia (AU)',
                                'IN' => 'India (IN)',
                                'Other' => 'Other International Country',
                            ])
                            ->default(fn (Handover $record) => $record->rentDetail->driver_licence_country ?: 'LK')
                            ->required(),

                        DatePicker::make('driver_licence_expiry')
                            ->label('Licence Expiry Date')
                            ->default(now()->addYears(2))
                            ->required(),

                        Checkbox::make('idp_or_aac_verified')
                            ->label('Physical International Driving Permit (IDP) or AAC endorsement inspected & verified (Mandatory for foreign licences)')
                            ->default(fn (Handover $record) => $record->rentDetail->has_idp),

                        Checkbox::make('passport_verified')
                            ->label('Physical Original Passport & Entry Visa verified in person (NO uploads)')
                            ->required(),

                        Textarea::make('notes')
                            ->label('Inspection Verification Notes')
                            ->placeholder('e.g. Inspected UK licence #... and valid 1949 IDP permit. Originals verified in person.')
                            ->required(),
                    ])
                    ->action(function (Handover $record, array $data, HandoverService $handoverService) {
                        try {
                            $docTypes = ['national_licence'];
                            if (! empty($data['passport_verified'])) {
                                $docTypes[] = 'passport';
                            }
                            if (! empty($data['idp_or_aac_verified'])) {
                                $docTypes[] = 'idp_or_aac';
                            }

                            $handoverService->recordDocumentInspection(
                                handover: $record,
                                staff: auth()->user(),
                                docTypes: $docTypes,
                                licenceCountry: (string) $data['driver_licence_country'],
                                licenceExpiry: ! empty($data['driver_licence_expiry']) ? Carbon::parse($data['driver_licence_expiry']) : null,
                                idpOrAacVerified: (bool) ($data['idp_or_aac_verified'] ?? false),
                                notes: (string) ($data['notes'] ?? '')
                            );

                            Notification::make()
                                ->title('Physical Documents Verified')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Verification Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // 2. Record Condition Action
                Action::make('recordCondition')
                    ->label('Record Condition')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('warning')
                    ->visible(fn (Handover $record) => ! $record->status->isTerminal())
                    ->form([
                        TextInput::make('odometer_km')
                            ->label('Current Odometer Reading (KM)')
                            ->numeric()
                            ->required(),

                        Radio::make('fuel_level')
                            ->label('Fuel Tank Level')
                            ->options([
                                'full' => 'Full (100%)',
                                'three_quarters' => '3/4 (75%)',
                                'half' => '1/2 (50%)',
                                'quarter' => '1/4 (25%)',
                                'empty' => 'Empty (0%)',
                            ])
                            ->default('full')
                            ->required(),

                        Checkbox::make('is_clean')
                            ->label('Vehicle is clean and sanitised inside & outside')
                            ->default(true),

                        Checkbox::make('smoking_detected')
                            ->label('Smoking / tobacco contamination detected (triggers $150 cleaning fee)'),

                        Checkbox::make('is_zero_km_verified')
                            ->label('Zero-KM return explicitly verified (required only if returned with 0 km driven on multi-day rental)'),

                        TextInput::make('zero_km_reason')
                            ->label('Zero-KM Verification Reason'),

                        Textarea::make('exterior_notes')
                            ->label('Exterior Body / Paint Condition Notes'),

                        Textarea::make('interior_notes')
                            ->label('Interior Cabin / Upholstery Condition Notes'),
                    ])
                    ->action(function (Handover $record, array $data, HandoverService $handoverService) {
                        try {
                            $handoverService->recordCondition(
                                handover: $record,
                                staff: auth()->user(),
                                odometerKm: (int) $data['odometer_km'],
                                fuelLevel: FuelLevel::from((string) $data['fuel_level']),
                                accessoriesChecklist: ['spare_tire' => true, 'jack' => true, 'tool_kit' => true],
                                isClean: (bool) ($data['is_clean'] ?? true),
                                smokingDetected: (bool) ($data['smoking_detected'] ?? false),
                                exteriorNotes: (string) ($data['exterior_notes'] ?? ''),
                                interiorNotes: (string) ($data['interior_notes'] ?? ''),
                                isZeroKmVerified: (bool) ($data['is_zero_km_verified'] ?? false),
                                zeroKmReason: (string) ($data['zero_km_reason'] ?? '')
                            );

                            Notification::make()
                                ->title('Vehicle Condition Captured')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Condition Recording Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // 3. Release Vehicle Action (Collection)
                Action::make('releaseVehicle')
                    ->label('Release Vehicle')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Handover $record) => $record->type === HandoverType::Collection && ! $record->is_released && ! $record->status->isTerminal())
                    ->action(function (Handover $record, HandoverService $handoverService) {
                        try {
                            $handoverService->releaseVehicle($record, auth()->user());

                            Notification::make()
                                ->title('Vehicle Successfully Released')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Release Blocked by Security Gates')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // 4. Four-Eyes Exception Authorization Action
                Action::make('authorizeException')
                    ->label('Manager Override')
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->color('danger')
                    ->visible(fn (Handover $record) => ! $record->is_released && auth()->user()?->can(Permission::BookingOverrideConflict->value))
                    ->form([
                        Textarea::make('exception_reason')
                            ->label('Audited Manager Exception Justification')
                            ->placeholder('State specific operational reason for overriding release gate requirements...')
                            ->required(),
                    ])
                    ->action(function (Handover $record, array $data, HandoverService $handoverService) {
                        try {
                            $handoverService->authorizeException($record, auth()->user(), (string) $data['exception_reason']);

                            Notification::make()
                                ->title('Release Exception Authorized')
                                ->warning()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Exception Authorization Rejected')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // 5. Complete Return Action
                Action::make('completeReturn')
                    ->label('Complete Return')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Handover $record) => $record->type === HandoverType::Return && $record->status !== HandoverStatus::Completed)
                    ->action(function (Handover $record, HandoverService $handoverService) {
                        try {
                            $handoverService->completeReturn($record, auth()->user());

                            Notification::make()
                                ->title('Vehicle Return Completed')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Return Completion Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHandovers::route('/'),
        ];
    }
}
