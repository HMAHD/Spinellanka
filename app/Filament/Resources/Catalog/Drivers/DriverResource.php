<?php

namespace App\Filament\Resources\Catalog\Drivers;

use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Services\DriverService;
use App\Filament\Resources\Catalog\Drivers\Pages\ManageDrivers;
use App\Models\Driver;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use UnitEnum;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static UnitEnum|string|null $navigationGroup = 'Catalog & Fleet';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Public Driver Profile')
                    ->description('Verified driver identity displayed to travelers.')
                    ->schema([
                        TextInput::make('public_name')
                            ->label('Public Display Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Driver name displayed to travelers (e.g. Sunil P.).'),
                        FileUpload::make('portrait_path')
                            ->label('Approved Portrait')
                            ->disk('public')
                            ->directory('drivers/portraits')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048),
                        TagsInput::make('public_trust_facts')
                            ->label('Public Trust Facts')
                            ->placeholder('Add trust marker (e.g. 10+ yrs chauffeuring, English fluent)'),
                    ]),

                Section::make('Operational & Compliance Details')
                    ->description('Internal contact information and licensing records.')
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Full Legal Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Primary Phone / Mobile')
                            ->tel()
                            ->required()
                            ->maxLength(50),
                        TextInput::make('whatsapp')
                            ->label('WhatsApp Contact')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('license_number')
                            ->label('Driving License Number')
                            ->maxLength(100),
                        DatePicker::make('license_expiry')
                            ->label('License Expiry Date'),
                        Select::make('status')
                            ->options(DriverStatus::class)
                            ->required()
                            ->default(DriverStatus::Active->value),
                        Textarea::make('internal_notes')
                            ->label('Internal Operational Notes')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('portrait_path')
                    ->label('Portrait')
                    ->disk('public')
                    ->circular(),
                TextColumn::make('public_name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('whatsapp'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('license_expiry')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginationMode(PaginationMode::Simple)
            ->recordActions([
                EditAction::make()
                    ->using(fn (Driver $record, array $data) => app(DriverService::class)->updateDriver($record, $data, auth()->user())),
                DeleteAction::make()
                    ->using(function (Driver $record): bool {
                        app(DriverService::class)->deleteDriver($record, auth()->user());

                        return true;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDrivers::route('/'),
        ];
    }
}
