<?php

namespace App\Filament\Resources\Catalog\Vehicles\RelationManagers;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Catalog\Enums\MediaType;
use App\Domain\Catalog\Services\MediaService;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file_path')
                    ->label('Upload Showcase Photo (Quarantined)')
                    ->disk('local')
                    ->directory('quarantine/vehicles')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120)
                    ->required()
                    ->helperText('All uploads are safely quarantined on private storage until approved.'),
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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alt_text')
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Photo')
                    ->disk(fn (VehicleMedia $record): string => $record->disk),
                TextColumn::make('media_type')
                    ->badge(),
                TextColumn::make('alt_text')
                    ->limit(40),
                IconColumn::make('is_approved')
                    ->boolean()
                    ->label('Approved'),
                TextColumn::make('disk')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'public' ? 'success' : 'warning'),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, RelationManager $livewire): Model {
                        /** @var Vehicle $vehicle */
                        $vehicle = $livewire->getOwnerRecord();
                        $auditService = app(AuditService::class);

                        $media = VehicleMedia::create([
                            'vehicle_id' => $vehicle->id,
                            'file_path' => $data['file_path'],
                            'disk' => 'local',
                            'media_type' => $data['media_type'],
                            'alt_text' => $data['alt_text'],
                            'photographer' => $data['photographer'] ?? null,
                            'usage_rights' => $data['usage_rights'] ?? null,
                            'sort_order' => (int) ($data['sort_order'] ?? 0),
                            'is_approved' => false,
                        ]);

                        $auditService->record(
                            action: 'fleet.media.uploaded',
                            actor: auth()->user(),
                            summary: "Uploaded media {$media->id} for vehicle {$vehicle->public_name} (Quarantined)",
                            metadata: [
                                'media_id' => $media->id,
                                'vehicle_id' => $vehicle->id,
                                'media_type' => $media->media_type->value,
                            ],
                            auditable: $media
                        );

                        return $media;
                    }),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve & Publish')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (VehicleMedia $record): bool => ! $record->is_approved)
                    ->action(fn (VehicleMedia $record) => app(MediaService::class)->approveAndPublish($record, auth()->user())),
                DeleteAction::make()
                    ->action(fn (VehicleMedia $record) => app(MediaService::class)->deleteMedia($record, auth()->user())),
            ]);
    }
}
