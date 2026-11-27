<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Privacy;

use App\Domain\Audit\Enums\ExportScope;
use App\Domain\Identity\Permission;
use App\Filament\Resources\Operations\Privacy\Pages\ManageDataExports;
use App\Models\DataExport;
use BackedEnum;
use Filament\Actions\Action;
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

class DataExportResource extends Resource
{
    protected static ?string $model = DataExport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static UnitEnum|string|null $navigationGroup = 'Privacy & Compliance';

    protected static ?string $navigationLabel = 'Data Exports';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::AuditExport->value) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can(Permission::AuditExport->value) ?? false;
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
                Section::make('Export Audit Details')
                    ->components([
                        TextInput::make('export_uuid')
                            ->label('Export UUID')
                            ->disabled(),

                        TextInput::make('requestedByUser.name')
                            ->label('Requested By')
                            ->disabled(),

                        TextInput::make('scope')
                            ->label('Scope')
                            ->formatStateUsing(fn (DataExport $record) => $record->scope->label())
                            ->disabled(),

                        TextInput::make('file_size_bytes')
                            ->label('File Size')
                            ->formatStateUsing(fn ($state) => sprintf('%s KB', number_format(((int) $state) / 1024, 2)))
                            ->disabled(),

                        TextInput::make('expires_at')
                            ->label('Expires At (UTC)')
                            ->disabled(),

                        TextInput::make('download_count')
                            ->label('Download Count')
                            ->disabled(),

                        TextInput::make('downloaded_at')
                            ->label('Last Downloaded At (UTC)')
                            ->disabled(),

                        Textarea::make('purpose')
                            ->label('Audited Purpose')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('export_uuid')
                    ->label('UUID')
                    ->copyable()
                    ->searchable()
                    ->limit(12),

                TextColumn::make('scope')
                    ->label('Scope')
                    ->badge()
                    ->color(fn (ExportScope $state): string => match ($state) {
                        ExportScope::CustomerBookingHistory => 'warning',
                        ExportScope::FinancialRecords => 'success',
                        ExportScope::OperationsAudit => 'info',
                    })
                    ->formatStateUsing(fn (ExportScope $state): string => $state->label()),

                TextColumn::make('requestedByUser.name')
                    ->label('Requested By')
                    ->searchable(),

                TextColumn::make('purpose')
                    ->label('Purpose')
                    ->limit(35)
                    ->tooltip(fn (DataExport $record): string => $record->purpose),

                TextColumn::make('file_size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn ($state): string => sprintf('%s KB', number_format(((int) $state) / 1024, 1))),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->color(fn (DataExport $record): string => $record->isExpired() ? 'danger' : 'gray'),

                TextColumn::make('download_count')
                    ->label('Downloads')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Generated At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('scope')
                    ->options([
                        ExportScope::CustomerBookingHistory->value => 'Customer Booking History',
                        ExportScope::FinancialRecords->value => 'Financial Ledger Records',
                        ExportScope::OperationsAudit->value => 'Operations Audit Trail',
                    ]),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('download')
                    ->label('Download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary')
                    ->url(fn (DataExport $record): string => route('operations.exports.download', ['uuid' => $record->export_uuid]))
                    ->openUrlInNewTab()
                    ->disabled(fn (DataExport $record): bool => $record->isExpired()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDataExports::route('/'),
        ];
    }
}
