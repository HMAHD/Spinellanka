<?php

declare(strict_types=1);

namespace App\Filament\Resources\Offers\Reviews;

use App\Domain\Booking\Enums\ReviewStatus;
use App\Domain\Identity\Permission;
use App\Domain\Offers\Services\ReviewService;
use App\Filament\Resources\Offers\Reviews\Pages\ManageReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static UnitEnum|string|null $navigationGroup = 'Offers & Promotions';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::OffersManage->value) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Verified Trip Review')
                    ->schema([
                        TextInput::make('traveler_name')
                            ->required()
                            ->maxLength(100),
                        Select::make('rating')
                            ->options([
                                5 => '⭐⭐⭐⭐⭐ 5 Stars — Excellent',
                                4 => '⭐⭐⭐⭐ 4 Stars — Very Good',
                                3 => '⭐⭐⭐ 3 Stars — Average',
                                2 => '⭐⭐ 2 Stars — Disappointing',
                                1 => '⭐ 1 Star — Poor',
                            ])
                            ->nullable(),
                        TextInput::make('title')
                            ->maxLength(120),
                        Textarea::make('review_text')
                            ->rows(4)
                            ->nullable(),
                    ]),

                Section::make('Moderation Decision')
                    ->schema([
                        Select::make('status')
                            ->options(ReviewStatus::class)
                            ->required(),
                        Toggle::make('is_featured')
                            ->label('Feature on Homepage')
                            ->default(false),
                        Toggle::make('verified_trip')
                            ->label('Verified Completed Trip')
                            ->default(true),
                        Textarea::make('moderation_notes')
                            ->rows(2)
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking.reference')
                    ->label('Booking Ref')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('traveler_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('service_type')
                    ->badge(),
                TextColumn::make('rating')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? str_repeat('★', $state) : 'Pending')
                    ->sortable(),
                TextColumn::make('title')
                    ->limit(25)
                    ->searchable(),
                TextColumn::make('review_text')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ReviewStatus $state): string => match ($state) {
                        ReviewStatus::Published => 'success',
                        ReviewStatus::PendingModeration => 'warning',
                        ReviewStatus::Rejected => 'danger',
                    }),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                IconColumn::make('verified_trip')
                    ->label('Verified')
                    ->boolean(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve & Publish')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Review $record): bool => $record->status !== ReviewStatus::Published)
                    ->action(fn (Review $record) => app(ReviewService::class)->moderateReview(
                        review: $record,
                        status: ReviewStatus::Published,
                        isFeatured: $record->is_featured,
                        notes: $record->moderation_notes,
                        moderator: auth()->user()
                    )),

                Action::make('toggle_featured')
                    ->label(fn (Review $record): string => $record->is_featured ? 'Unfeature' : 'Feature on Homepage')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('warning')
                    ->visible(fn (Review $record): bool => $record->status === ReviewStatus::Published)
                    ->action(fn (Review $record) => app(ReviewService::class)->moderateReview(
                        review: $record,
                        status: $record->status,
                        isFeatured: ! $record->is_featured,
                        notes: $record->moderation_notes,
                        moderator: auth()->user()
                    )),

                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Review $record): bool => $record->status !== ReviewStatus::Rejected)
                    ->action(fn (Review $record) => app(ReviewService::class)->moderateReview(
                        review: $record,
                        status: ReviewStatus::Rejected,
                        isFeatured: false,
                        notes: $record->moderation_notes,
                        moderator: auth()->user()
                    )),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }
}
