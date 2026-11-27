<?php

declare(strict_types=1);

namespace App\Filament\Resources\Offers\Deals;

use App\Domain\Booking\Enums\DealStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Filament\Resources\Offers\Deals\Pages\ManageDeals;
use App\Models\Deal;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DealResource extends Resource
{
    protected static ?string $model = Deal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static UnitEnum|string|null $navigationGroup = 'Offers & Promotions';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::OffersManage->value) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Package Information')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('service_type')
                            ->options(ServiceType::class)
                            ->required(),
                        Textarea::make('summary')
                            ->rows(2)
                            ->required(),
                        Textarea::make('description_markdown')
                            ->label('Itinerary & Description (Markdown)')
                            ->rows(5)
                            ->required(),
                        KeyValue::make('preset_payload')
                            ->label('Service Criteria Preset (Key/Value)')
                            ->helperText('Defaults for destination, passenger_count, duration_days, etc.'),
                        Select::make('promotion_id')
                            ->label('Attached Promotion')
                            ->relationship('promotion', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ]),

                Section::make('Approved Fleet Media')
                    ->schema([
                        TextInput::make('image_path')
                            ->label('Approved Photo URL / Path')
                            ->maxLength(500)
                            ->nullable(),
                        TextInput::make('image_alt')
                            ->label('Image Alt Text')
                            ->maxLength(255)
                            ->nullable(),
                        TextInput::make('image_source')
                            ->label('Photo Attribution / Source')
                            ->maxLength(255)
                            ->nullable(),
                        TextInput::make('image_approver')
                            ->label('Approved By (Staff Name)')
                            ->maxLength(255)
                            ->nullable(),
                        DateTimePicker::make('image_approved_at')
                            ->label('Approval Timestamp')
                            ->nullable(),
                    ]),

                Section::make('Status & Publication')
                    ->schema([
                        Select::make('status')
                            ->options(DealStatus::class)
                            ->default(DealStatus::Draft->value)
                            ->required(),
                        Toggle::make('is_featured')
                            ->label('Feature on Homepage')
                            ->default(false),
                        TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('Published At (Asia/Colombo)')
                            ->nullable(),
                        DateTimePicker::make('expires_at')
                            ->label('Expires At (Asia/Colombo)')
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('service_type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (DealStatus $state): string => match ($state) {
                        DealStatus::Published => 'success',
                        DealStatus::Draft => 'warning',
                        DealStatus::Archived => 'gray',
                    }),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                TextColumn::make('priority')
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDeals::route('/'),
        ];
    }
}
