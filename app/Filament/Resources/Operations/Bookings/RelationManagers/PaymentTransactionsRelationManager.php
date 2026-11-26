<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Bookings\RelationManagers;

use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Models\PaymentTransaction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentTransactions';

    protected static ?string $title = 'Payment & Refund Ledger';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('idempotency_key')
            ->defaultSort('id', 'desc')
            ->columns([
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
                    ->placeholder('N/A')
                    ->copyable()
                    ->limit(16),

                TextColumn::make('idempotency_key')
                    ->label('Idempotency Key')
                    ->copyable()
                    ->limit(16),

                TextColumn::make('settled_at')
                    ->label('Settled At')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Pending')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Recorded')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }
}
