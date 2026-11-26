<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Incidents\Pages;

use App\Domain\Identity\Permission;
use App\Domain\Operations\Enums\IncidentSeverity;
use App\Domain\Operations\Enums\IncidentType;
use App\Domain\Operations\Services\IncidentService;
use App\Filament\Resources\Operations\Incidents\IncidentResource;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\Vehicle;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageIncidents extends ManageRecords
{
    protected static string $resource = IncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Report Incident')
                ->visible(fn () => auth()->user()?->can(Permission::IncidentManage->value))
                ->form([
                    Select::make('type')
                        ->label('Incident Category')
                        ->options([
                            'accident' => 'Traffic Collision / Accident',
                            'breakdown' => 'Mechanical Breakdown',
                            'damage' => 'Vehicle Physical Damage',
                            'theft' => 'Theft / Vandalism',
                            'traffic_violation' => 'Traffic Fine / Police Violation',
                            'customer_dispute' => 'Customer Dispute',
                            'service_delay' => 'Major Service Interruption / Delay',
                        ])
                        ->required(),

                    Select::make('severity')
                        ->label('Severity Tier')
                        ->options([
                            'minor' => 'Minor (Cosmetic / No disruption)',
                            'moderate' => 'Moderate (Repairable / Brief delay)',
                            'severe' => 'Severe (Towing required / Police involved)',
                            'critical' => 'Critical (Injury / Total loss / Major legal)',
                        ])
                        ->default('minor')
                        ->required(),

                    Select::make('booking_id')
                        ->label('Linked Booking (Optional)')
                        ->options(fn () => Booking::latest('id')->take(50)->pluck('reference', 'id'))
                        ->searchable(),

                    Select::make('vehicle_id')
                        ->label('Linked Vehicle (Optional)')
                        ->options(fn () => Vehicle::pluck('license_plate', 'id'))
                        ->searchable(),

                    Select::make('driver_id')
                        ->label('Linked Driver (Optional)')
                        ->options(fn () => Driver::pluck('full_name', 'id'))
                        ->searchable(),

                    DateTimePicker::make('occurred_at')
                        ->label('Occurred At (Colombo)')
                        ->default(now('Asia/Colombo'))
                        ->required(),

                    TextInput::make('location_text')
                        ->label('Incident Location')
                        ->placeholder('e.g. Kandy-Colombo Road, Kadugannawa'),

                    Textarea::make('description')
                        ->label('Factual Incident Narrative')
                        ->required(),
                ])
                ->using(function (array $data, IncidentService $incidentService) {
                    try {
                        return $incidentService->createIncident(
                            staff: auth()->user(),
                            data: [
                                'type' => IncidentType::from((string) $data['type']),
                                'severity' => IncidentSeverity::from((string) $data['severity']),
                                'booking_id' => ! empty($data['booking_id']) ? (int) $data['booking_id'] : null,
                                'vehicle_id' => ! empty($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
                                'driver_id' => ! empty($data['driver_id']) ? (int) $data['driver_id'] : null,
                                'occurred_at' => Carbon::parse($data['occurred_at']),
                                'location_text' => (string) ($data['location_text'] ?? ''),
                                'description' => (string) $data['description'],
                            ]
                        );
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Incident Creation Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        throw $e;
                    }
                }),
        ];
    }
}
