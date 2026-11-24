<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\ScheduleBlocks\Pages;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Exceptions\ScheduleConflictException;
use App\Domain\Availability\Exceptions\UnauthorizedOverrideException;
use App\Domain\Availability\Services\AvailabilityService;
use App\Filament\Resources\Operations\ScheduleBlocks\ScheduleBlockResource;
use App\Models\Vehicle;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class ManageScheduleBlocks extends ManageRecords
{
    protected static string $resource = ScheduleBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    /** @var Vehicle $vehicle */
                    $vehicle = Vehicle::findOrFail($data['vehicle_id']);
                    $kind = ScheduleBlockKind::from($data['kind']);
                    $startsAt = Carbon::parse($data['starts_at'])->setTimezone('Asia/Colombo');
                    $endsAt = Carbon::parse($data['ends_at'])->setTimezone('Asia/Colombo');
                    $bufferBefore = (int) ($data['buffer_minutes_before'] ?? 0);
                    $bufferAfter = (isset($data['buffer_minutes_after']) && $data['buffer_minutes_after'] !== '')
                        ? (int) $data['buffer_minutes_after']
                        : null;

                    try {
                        return app(AvailabilityService::class)->createBlock(
                            vehicle: $vehicle,
                            kind: $kind,
                            start: $startsAt,
                            end: $endsAt,
                            bufferBefore: $bufferBefore,
                            bufferAfter: $bufferAfter,
                            options: [
                                'driver_id' => $data['driver_id'] ?? null,
                                'is_chauffeured_only' => $kind === ScheduleBlockKind::DriverUnavailability,
                                'notes' => $data['notes'] ?? null,
                                'is_override' => (bool) ($data['is_override'] ?? false),
                                'override_reason' => $data['override_reason'] ?? null,
                            ],
                            actor: auth()->user(),
                        );
                    } catch (ScheduleConflictException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Schedule Conflict')
                            ->body($e->getMessage())
                            ->send();

                        throw (new Halt)->rollBackDatabaseTransaction(false);
                    } catch (UnauthorizedOverrideException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Unauthorized Conflict Override')
                            ->body($e->getMessage())
                            ->send();

                        throw (new Halt)->rollBackDatabaseTransaction(false);
                    }
                }),
        ];
    }
}
