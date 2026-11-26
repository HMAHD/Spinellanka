<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Payments;

use App\Domain\Identity\Permission;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Filament\Resources\Operations\Bookings\BookingResource;
use App\Filament\Resources\Operations\Payments\Pages\ManagePaymentTransactions;
use App\Models\PaymentTransaction;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PaymentTransactionResource extends Resource
{
    protected static ?string $model = PaymentTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Payment Ledger';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::PaymentView->value) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can(Permission::PaymentView->value) ?? false;
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
                Section::make('Transaction Details')
                    ->schema([
                        TextInput::make('idempotency_key')
                            ->disabled(),
                        TextInput::make('gateway_transaction_id')
                            ->label('Gateway Reference')
                            ->disabled(),
                        TextInput::make('payment_type')
                            ->formatStateUsing(fn ($state) => $state instanceof PaymentType ? $state->label() : (string) $state)
                            ->disabled(),
                        TextInput::make('payment_method')
                            ->formatStateUsing(fn ($state) => $state instanceof PaymentMethod ? $state->label() : (string) $state)
                            ->disabled(),
                        TextInput::make('amount_formatted')
                            ->label('Amount')
                            ->afterStateHydrated(fn (TextInput $component, ?PaymentTransaction $record) => $component->state(
                                $record ? sprintf('%s %.2f', $record->currency, $record->amount_minor_units / 100) : 'N/A'
                            ))
                            ->disabled(),
                        TextInput::make('status')
                            ->formatStateUsing(fn ($state) => $state instanceof PaymentStatus ? $state->label() : (string) $state)
                            ->disabled(),
                        TextInput::make('settled_at')
                            ->disabled(),
                        TextInput::make('created_at')
                            ->disabled(),
                    ])->columns(4),

                Section::make('Audit Metadata')
                    ->schema([
                        Textarea::make('metadata')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('idempotency_key')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('booking.reference')
                    ->label('Booking Ref')
                    ->searchable()
                    ->weight('bold')
                    ->url(fn (PaymentTransaction $record): string => BookingResource::getUrl('index', [
                        'tableSearch' => $record->booking?->reference,
                    ])),

                TextColumn::make('payment_type')
                    ->badge()
                    ->formatStateUsing(fn (PaymentType $state): string => $state->label())
                    ->color(fn (PaymentType $state): string => match ($state) {
                        PaymentType::Full, PaymentType::Balance => 'success',
                        PaymentType::Deposit, PaymentType::RentExtension => 'primary',
                        PaymentType::SecurityDeposit => 'info',
                        PaymentType::Refund => 'danger',
                        PaymentType::IncidentCharge, PaymentType::ConditionSettlement, PaymentType::CleaningPenalty => 'warning',
                    }),

                TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label())
                    ->color('gray'),

                TextColumn::make('amount_minor_units')
                    ->label('Amount')
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, PaymentTransaction $record) => sprintf('%s %.2f', $record->currency, $state / 100))
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::Completed => 'success',
                        PaymentStatus::Pending => 'warning',
                        PaymentStatus::Failed => 'danger',
                        PaymentStatus::Cancelled => 'gray',
                        PaymentStatus::Refunded => 'danger',
                        PaymentStatus::PartiallyRefunded, PaymentStatus::Discrepancy => 'warning',
                    }),

                TextColumn::make('gateway_transaction_id')
                    ->label('Gateway ID')
                    ->searchable()
                    ->limit(16),

                TextColumn::make('received_at')
                    ->label('Settled At')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Pending')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Initiated At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
                SelectFilter::make('payment_method')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
                SelectFilter::make('payment_type')
                    ->options(collect(PaymentType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentTransactions::route('/'),
        ];
    }
}
