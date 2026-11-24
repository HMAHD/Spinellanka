<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Bookings;

use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Identity\Permission;
use App\Filament\Resources\Operations\Bookings\Pages\ManageBookings;
use App\Models\Booking;
use BackedEnum;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Bookings';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Booking Overview')
                    ->schema([
                        TextInput::make('reference')
                            ->disabled(),
                        TextInput::make('status')
                            ->formatStateUsing(fn ($state) => $state instanceof BookingStatus ? $state->label() : (string) $state)
                            ->disabled(),
                        TextInput::make('source')
                            ->formatStateUsing(fn ($state) => $state instanceof BookingSource ? $state->label() : (string) $state)
                            ->disabled(),
                        TextInput::make('created_at')
                            ->disabled(),
                    ])->columns(4),

                Section::make('Flight & Destination')
                    ->schema([
                        TextInput::make('flight_number')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state($record?->airportPickupDetail?->flight_number))
                            ->disabled(),
                        TextInput::make('arrival_time')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state($record?->airportPickupDetail?->arrival_time?->format('Y-m-d H:i').' (Asia/Colombo)'))
                            ->disabled(),
                        TextInput::make('destination_text')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state($record?->airportPickupDetail?->destination_text))
                            ->disabled(),
                        TextInput::make('passengers_luggage')
                            ->label('Party Size')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state("{$record?->airportPickupDetail?->passenger_count} Pass / {$record?->airportPickupDetail?->luggage_count} Bags"))
                            ->disabled(),
                    ])->columns(2),

                Section::make('Vehicle Allocation & Financials')
                    ->schema([
                        TextInput::make('vehicle_info')
                            ->label('Allocated Vehicle')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state("{$record?->vehicle?->make} {$record?->vehicle?->model} ({$record?->vehicle?->license_plate})"))
                            ->disabled(),
                        TextInput::make('total_amount')
                            ->label('Total Estimated Amount')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state($record?->total_amount_minor_units ? 'USD $'.number_format($record->total_amount_minor_units / 100, 2) : 'Custom Quote'))
                            ->disabled(),
                        TextInput::make('approx_lkr')
                            ->label('Approximate LKR Basis')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state($record?->approximate_lkr_amount_minor_units ? 'LKR '.number_format($record->approximate_lkr_amount_minor_units / 100, 0) : 'N/A'))
                            ->disabled(),
                    ])->columns(3),

                Section::make('Manual Confirmation Record')
                    ->schema([
                        TextInput::make('confirmation_channel')
                            ->formatStateUsing(fn ($state) => $state instanceof ConfirmationChannel ? $state->label() : (string) $state)
                            ->disabled(),
                        TextInput::make('confirmed_at')
                            ->disabled(),
                        Textarea::make('confirmation_notes')
                            ->columnSpanFull()
                            ->disabled(),
                    ])
                    ->visible(fn (?Booking $record) => $record?->status === BookingStatus::Confirmed)
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehicle', 'airportPickupDetail', 'booker', 'leadTraveler']))
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (BookingStatus $state): string => match ($state) {
                        BookingStatus::Submitted => 'warning',
                        BookingStatus::UnderReview => 'info',
                        BookingStatus::Confirmed => 'success',
                        BookingStatus::Declined => 'danger',
                        BookingStatus::Cancelled, BookingStatus::Expired => 'gray',
                    })
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label()),

                TextColumn::make('airportPickupDetail.flight_number')
                    ->label('Flight')
                    ->searchable(),

                TextColumn::make('airportPickupDetail.arrival_time')
                    ->label('Arrival (Colombo)')
                    ->dateTime('d M Y H:i', 'Asia/Colombo')
                    ->sortable(),

                TextColumn::make('airportPickupDetail.destination_text')
                    ->label('Destination')
                    ->limit(25)
                    ->searchable(),

                TextColumn::make('vehicle.license_plate')
                    ->label('Vehicle')
                    ->formatStateUsing(fn ($state, Booking $record) => "{$record->vehicle?->make} ({$state})"),

                TextColumn::make('leadTraveler.phone')
                    ->label('Contact')
                    ->formatStateUsing(fn ($state, Booking $record) => $record->leadTraveler ? "{$record->leadTraveler->first_name} ({$state})" : 'N/A'),

                TextColumn::make('total_amount_minor_units')
                    ->label('Estimate')
                    ->formatStateUsing(fn (?int $state) => $state ? 'USD $'.number_format($state / 100, 2) : 'Custom Quote'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BookingStatus::cases())->mapWithKeys(fn (BookingStatus $status) => [$status->value => $status->label()])),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('review')
                    ->label('Review')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Submitted)
                    ->requiresConfirmation()
                    ->action(function (Booking $record): void {
                        app(BookingLifecycleService::class)->markUnderReview($record, auth()->user());
                        Notification::make()->title('Booking marked under review.')->success()->send();
                    }),

                Action::make('confirm')
                    ->label('Confirm')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Booking $record): bool => $record->canBeConfirmed())
                    ->modalHeading('Confirm Airport Pickup Booking')
                    ->modalDescription('Verify direct customer communication outcome and schedule availability before confirming.')
                    ->form([
                        Select::make('confirmation_channel')
                            ->label('Customer Communication Channel')
                            ->options(collect(ConfirmationChannel::cases())->mapWithKeys(fn (ConfirmationChannel $c) => [$c->value => $c->label()]))
                            ->default(ConfirmationChannel::WhatsApp->value)
                            ->required(),

                        Textarea::make('confirmation_notes')
                            ->label('Communication Outcome Notes')
                            ->placeholder('e.g. Communicated with lead traveler on WhatsApp (+44...), verified flight arrival and meet & greet location.')
                            ->required()
                            ->rows(3),

                        Textarea::make('override_reason')
                            ->label('Conflict Override Reason (If schedule conflict exists)')
                            ->placeholder('Document approval justification for overriding any schedule conflict.')
                            ->visible(fn () => auth()->user()?->can(Permission::BookingOverrideConflict->value) ?? false)
                            ->rows(2),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $channel = ConfirmationChannel::from($data['confirmation_channel']);
                        app(BookingLifecycleService::class)->confirm(
                            booking: $record,
                            staff: auth()->user(),
                            channel: $channel,
                            notes: $data['confirmation_notes'],
                            overrideReason: $data['override_reason'] ?? null
                        );
                        Notification::make()->title("Booking {$record->reference} confirmed.")->success()->send();
                    }),

                Action::make('decline')
                    ->label('Decline')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Booking $record): bool => $record->canBeDeclined())
                    ->modalHeading('Decline Booking Request')
                    ->form([
                        Textarea::make('decline_reason')
                            ->label('Decline Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        app(BookingLifecycleService::class)->decline($record, auth()->user(), $data['decline_reason']);
                        Notification::make()->title("Booking {$record->reference} declined.")->danger()->send();
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('gray')
                    ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Confirmed)
                    ->form([
                        Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        app(BookingLifecycleService::class)->cancel($record, $data['cancellation_reason'], auth()->user());
                        Notification::make()->title("Booking {$record->reference} cancelled.")->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBookings::route('/'),
        ];
    }
}
