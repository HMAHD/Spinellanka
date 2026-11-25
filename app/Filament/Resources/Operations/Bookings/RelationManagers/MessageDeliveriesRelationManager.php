<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Bookings\RelationManagers;

use App\Domain\Identity\Permission;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Domain\Messaging\Enums\RecipientType;
use App\Domain\Messaging\Services\MessageDeliveryService;
use App\Models\MessageDelivery;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MessageDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'messageDeliveries';

    protected static ?string $title = 'Message Deliveries';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('recipient_identifier')
            ->columns([
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (MessageChannel $state): string => $state->label())
                    ->color(fn (MessageChannel $state): string => $state === MessageChannel::WhatsApp ? 'success' : 'info'),

                TextColumn::make('recipient_type')
                    ->label('Role')
                    ->formatStateUsing(fn ($state): string => ucfirst(str_replace('_', ' ', $state instanceof RecipientType ? $state->value : (string) $state))),

                TextColumn::make('recipient_identifier')
                    ->label('Destination')
                    ->formatStateUsing(fn (MessageDelivery $record): string => $record->maskedRecipient()),

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

                TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('D, d M H:i')
                    ->placeholder('—'),

                TextColumn::make('last_error_message')
                    ->label('Error')
                    ->limit(25)
                    ->placeholder('—'),
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
}
