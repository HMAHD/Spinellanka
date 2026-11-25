<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\MessageDeliveries;

use App\Domain\Identity\Permission;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Domain\Messaging\Enums\RecipientType;
use App\Domain\Messaging\Services\MessageDeliveryService;
use App\Filament\Resources\Operations\MessageDeliveries\Pages\ManageMessageDeliveries;
use App\Models\MessageDelivery;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MessageDeliveryResource extends Resource
{
    protected static ?string $model = MessageDelivery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Message Deliveries';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->can(Permission::BookingView->value)
            || $user->can(Permission::BookingReview->value)
            || $user->can(Permission::BookingConfirm->value);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking.reference')
                    ->label('Booking Ref')
                    ->searchable()
                    ->sortable()
                    ->default('N/A'),

                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (MessageChannel $state): string => $state->label())
                    ->color(fn (MessageChannel $state): string => $state === MessageChannel::WhatsApp ? 'success' : 'info'),

                TextColumn::make('recipient_type')
                    ->label('Role')
                    ->formatStateUsing(fn ($state): string => ucfirst(str_replace('_', ' ', $state instanceof RecipientType ? $state->value : (string) $state))),

                TextColumn::make('recipient_identifier')
                    ->label('Destination')
                    ->formatStateUsing(fn (MessageDelivery $record): string => $record->maskedRecipient())
                    ->searchable(),

                TextColumn::make('template_name')
                    ->label('Template')
                    ->limit(24),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (MessageDeliveryStatus $state): string => $state->label())
                    ->color(fn (MessageDeliveryStatus $state): string => $state->badgeColor()),

                TextColumn::make('attempts')
                    ->label('Attempts')
                    ->formatStateUsing(fn (MessageDelivery $record): string => "{$record->attempts}/{$record->max_attempts}"),

                TextColumn::make('provider_message_id')
                    ->label('Provider ID')
                    ->limit(16)
                    ->default('—'),

                TextColumn::make('last_error_message')
                    ->label('Error')
                    ->limit(30)
                    ->tooltip(function (MessageDelivery $record): ?string {
                        if (! $record->last_error_message) {
                            return null;
                        }
                        $sanitized = preg_replace('/Bearer\s+[A-Za-z0-9_\-\.]+/i', 'Bearer [REDACTED]', $record->last_error_message);
                        $sanitized = preg_replace('/\b\+?\d{9,15}\b/', '[REDACTED_PHONE]', (string) $sanitized);

                        return mb_substr((string) $sanitized, 0, 200);
                    }),

                TextColumn::make('created_at')
                    ->label('Queued')
                    ->dateTime('D, d M H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'email' => 'Email',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'delivery_unknown' => 'Delivery Unknown',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                ViewAction::make()
                    ->mutateRecordDataUsing(function (array $data, MessageDelivery $record): array {
                        $data['recipient_identifier'] = $record->maskedRecipient();

                        return $data;
                    }),

                Action::make('retry')
                    ->label('Retry')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (MessageDelivery $record): bool => in_array($record->status, [MessageDeliveryStatus::Failed, MessageDeliveryStatus::DeliveryUnknown], true)
                        && (auth()->user()?->can(Permission::BookingReview->value) || auth()->user()?->can(Permission::BookingConfirm->value)))
                    ->authorize(fn (MessageDelivery $record): bool => in_array($record->status, [MessageDeliveryStatus::Failed, MessageDeliveryStatus::DeliveryUnknown], true)
                        && (auth()->user()?->can(Permission::BookingReview->value) || auth()->user()?->can(Permission::BookingConfirm->value)))
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Manual Message Delivery Retry')
                    ->modalDescription('Warning: If the customer already received this notification, manual re-sending may produce a duplicate message. Confirm only if delivery failure is verified.')
                    ->action(function (MessageDelivery $record): void {
                        /** @var User $actor */
                        $actor = auth()->user();

                        app(MessageDeliveryService::class)->manualRetry($record, $actor);

                        Notification::make()
                            ->title('Message delivery queued for manual retry.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMessageDeliveries::route('/'),
        ];
    }
}
