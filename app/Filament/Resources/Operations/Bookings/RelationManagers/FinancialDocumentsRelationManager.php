<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Bookings\RelationManagers;

use App\Domain\Payment\Enums\FinancialDocumentType;
use App\Models\FinancialDocument;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FinancialDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'financialDocuments';

    protected static ?string $title = 'Issued Financial Documents';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('document_number')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('document_type')
                    ->badge()
                    ->formatStateUsing(fn (FinancialDocumentType $state): string => $state->label())
                    ->color(fn (FinancialDocumentType $state): string => match ($state) {
                        FinancialDocumentType::Receipt => 'success',
                        FinancialDocumentType::FinalInvoice => 'info',
                        FinancialDocumentType::RentalAgreement => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('document_number')
                    ->label('Document #')
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('amount_minor_units')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, FinancialDocument $record) => sprintf('%s %.2f', $record->currency, $state / 100)),

                TextColumn::make('checksum')
                    ->label('SHA256 Checksum')
                    ->fontFamily('mono')
                    ->limit(16)
                    ->copyable(),

                TextColumn::make('issued_at')
                    ->label('Issued At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Action::make('view_document')
                    ->label('View Document')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->url(fn (FinancialDocument $record): string => route('staff.documents.view', [
                        'booking' => $record->booking_id,
                        'documentNumber' => $record->document_number,
                    ]))
                    ->openUrlInNewTab(),
            ]);
    }
}
