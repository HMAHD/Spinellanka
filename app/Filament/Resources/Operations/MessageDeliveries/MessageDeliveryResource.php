<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\MessageDeliveries;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Permission;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Domain\Messaging\Enums\RecipientType;
use App\Filament\Resources\Operations\MessageDeliveries\Pages\ManageMessageDeliveries;
use App\Jobs\SendMessageDeliveryJob;
use App\Models\MessageDelivery;
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
                    ->tooltip(fn (MessageDelivery $record): ?string => $record->last_error_message),

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
                ViewAction::make(),

                Action::make('retry')
                    ->label('Retry')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (MessageDelivery $record): bool => in_array($record->status, [MessageDeliveryStatus::Failed, MessageDeliveryStatus::DeliveryUnknown], true)
                        && (auth()->user()?->can(Permission::BookingReview->value) || auth()->user()?->can(Permission::BookingConfirm->value)))
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Manual Message Delivery Retry')
                    ->modalDescription('Warning: If the customer already received this notification, manual re-sending may produce a duplicate message. Confirm only if delivery failure is verified.')
                    ->action(function (MessageDelivery $record): void {
                        $record->update([
                            'status' => MessageDeliveryStatus::Pending,
                            'max_attempts' => $record->max_attempts + 1,
                        ]);

                        SendMessageDeliveryJob::dispatch($record->id);

                        $channelName = $record->channel instanceof MessageChannel ? $record->channel->value : (string) $record->channel;

                        app(AuditService::class)->record(
                            action: 'message_delivery.manually_retried',
                            actor: auth()->user(),
                            summary: "Manually re-queued {$channelName} delivery for booking {$record->booking?->reference} to {$record->maskedRecipient()}",
                            metadata: [
                                'message_delivery_id' => $record->id,
                                'booking_id' => $record->booking_id,
                                'channel' => $channelName,
                                'recipient' => $record->maskedRecipient(),
                                'template' => $record->template_name,
                            ],
                            auditable: $record->booking
                        );

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
