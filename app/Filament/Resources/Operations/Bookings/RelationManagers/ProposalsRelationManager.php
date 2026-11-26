<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Bookings\RelationManagers;

use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\Services\ProposalService;
use App\Models\Booking;
use App\Models\Proposal;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class ProposalsRelationManager extends RelationManager
{
    protected static string $relationship = 'proposals';

    protected static ?string $title = 'Versioned Proposals';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')
                    ->label('Ver')
                    ->formatStateUsing(fn ($state) => "v{$state}")
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('reference')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ProposalStatus $state): string => match ($state) {
                        ProposalStatus::Draft => 'gray',
                        ProposalStatus::Sent => 'info',
                        ProposalStatus::Accepted => 'success',
                        ProposalStatus::Declined => 'danger',
                        ProposalStatus::Superseded => 'gray',
                        ProposalStatus::Expired => 'warning',
                    })
                    ->formatStateUsing(fn (ProposalStatus $state): string => $state->label()),

                TextColumn::make('vehicle.license_plate')
                    ->label('Vehicle')
                    ->formatStateUsing(fn ($state, Proposal $record) => $record->vehicle ? "{$record->vehicle->make} ({$state})" : 'N/A'),

                TextColumn::make('total_amount_minor_units')
                    ->label('Total Price')
                    ->formatStateUsing(fn ($state, Proposal $record) => sprintf('%s %.2f', $record->currency, $state / 100)),

                TextColumn::make('deposit_amount_minor_units')
                    ->label('Deposit')
                    ->formatStateUsing(fn ($state, Proposal $record) => $record->deposit_required ? sprintf('%s %.2f', $record->currency, $state / 100) : 'None'),

                TextColumn::make('payment_choice')
                    ->label('Payment Terms')
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->limit(20),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('d M H:i')
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime('d M H:i')
                    ->sortable(),

                TextColumn::make('responded_at')
                    ->label('Responded')
                    ->dateTime('d M H:i')
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('compare')
                    ->label('Compare')
                    ->icon(Heroicon::OutlinedScale)
                    ->color('info')
                    ->modalHeading(fn (Proposal $record): string => "Comparison: {$record->reference} vs Initial Request")
                    ->modalContent(function (Proposal $record): View {
                        /** @var Booking $booking */
                        $booking = $record->booking;
                        $comparison = app(ProposalService::class)->computeComparison($booking, $record);

                        return view('filament.modals.proposal-comparison', [
                            'proposal' => $record,
                            'comparison' => $comparison,
                        ]);
                    }),
            ]);
    }
}
