<?php

namespace App\Filament\Resources\Catalog\Vehicles\RelationManagers;

use App\Domain\Catalog\Enums\MediaType;
use App\Domain\Catalog\Services\MediaService;
use App\Models\VehicleMedia;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file_path')
                    ->label('Showcase Photo')
                    ->disk('public')
                    ->directory('vehicles/media')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120)
                    ->required(),
                Select::make('media_type')
                    ->options(MediaType::class)
                    ->required(),
                TextInput::make('alt_text')
                    ->label('Accessibility Alt Text')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Factual, accessible descriptive text for screen readers.'),
                TextInput::make('photographer')
                    ->label('Photographer Credit')
                    ->maxLength(255),
                TextInput::make('usage_rights')
                    ->label('Usage Rights / License')
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_approved')
                    ->label('Approved & Published')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alt_text')
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Photo')
                    ->disk('public'),
                TextColumn::make('media_type')
                    ->badge(),
                TextColumn::make('alt_text')
                    ->limit(40),
                IconColumn::make('is_approved')
                    ->boolean()
                    ->label('Approved'),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (VehicleMedia $record): bool => ! $record->is_approved)
                    ->action(fn (VehicleMedia $record) => app(MediaService::class)->approveAndPublish($record, auth()->user())),
                DeleteAction::make()
                    ->action(fn (VehicleMedia $record) => app(MediaService::class)->deleteMedia($record, auth()->user())),
            ]);
    }
}

