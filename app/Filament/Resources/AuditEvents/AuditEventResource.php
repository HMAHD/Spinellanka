<?php

namespace App\Filament\Resources\AuditEvents;

use App\Filament\Resources\AuditEvents\Pages\ManageAuditEvents;
use App\Models\AuditEvent;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AuditEventResource extends Resource
{
    protected static ?string $model = AuditEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Audit & Security';

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
                TextInput::make('created_at')
                    ->label('Timestamp (UTC)')
                    ->disabled(),
                TextInput::make('action')
                    ->disabled(),
                TextInput::make('actor_name')
                    ->label('Actor Name')
                    ->disabled(),
                TextInput::make('actor_email')
                    ->label('Actor Email')
                    ->disabled(),
                TextInput::make('actor_type')
                    ->disabled(),
                TextInput::make('ip_address')
                    ->disabled(),
                Textarea::make('user_agent')
                    ->disabled()
                    ->columnSpanFull(),
                TextInput::make('summary')
                    ->disabled()
                    ->columnSpanFull(),
                KeyValue::make('metadata')
                    ->label('Event Metadata')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp (UTC)')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('action')
                    ->badge()
                    ->searchable(),
                TextColumn::make('actor_name')
                    ->label('Actor')
                    ->searchable(),
                TextColumn::make('actor_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('summary')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationMode(PaginationMode::Simple)
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditEvents::route('/'),
        ];
    }
}
