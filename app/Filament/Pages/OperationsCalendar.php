<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Identity\Permission;
use App\Domain\Operations\Services\OperationsAlertService;
use App\Domain\Operations\Services\OperationsScheduleProjectionService;
use App\Models\Vehicle;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class OperationsCalendar extends Page
{
    protected string $view = 'filament.pages.operations-calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Operations Calendar';

    protected static ?int $navigationSort = 1;

    public string $viewMode = 'month';

    public string $activeDate;

    public ?int $vehicleId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can(Permission::BookingView->value) || $user?->can(Permission::FleetView->value));
    }

    public function mount(): void
    {
        $this->activeDate = Carbon::now('Asia/Colombo')->format('Y-m-d');
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['month', 'week', 'day', 'agenda'], true)) {
            $this->viewMode = $mode;
        }
    }

    public function setVehicleId(?int $id): void
    {
        $this->vehicleId = $id ?: null;
    }

    public function previousPeriod(): void
    {
        $date = Carbon::parse($this->activeDate, 'Asia/Colombo');
        $this->activeDate = match ($this->viewMode) {
            'month' => $date->subMonth()->format('Y-m-d'),
            'week' => $date->subWeek()->format('Y-m-d'),
            'day' => $date->subDay()->format('Y-m-d'),
            'agenda' => $date->subDays(7)->format('Y-m-d'),
            default => $date->subMonth()->format('Y-m-d'),
        };
    }

    public function nextPeriod(): void
    {
        $date = Carbon::parse($this->activeDate, 'Asia/Colombo');
        $this->activeDate = match ($this->viewMode) {
            'month' => $date->addMonth()->format('Y-m-d'),
            'week' => $date->addWeek()->format('Y-m-d'),
            'day' => $date->addDay()->format('Y-m-d'),
            'agenda' => $date->addDays(7)->format('Y-m-d'),
            default => $date->addMonth()->format('Y-m-d'),
        };
    }

    public function today(): void
    {
        $this->activeDate = Carbon::now('Asia/Colombo')->format('Y-m-d');
    }

    public function getViewData(): array
    {
        $projectionService = app(OperationsScheduleProjectionService::class);
        $alertService = app(OperationsAlertService::class);
        $date = Carbon::parse($this->activeDate, 'Asia/Colombo');

        $items = match ($this->viewMode) {
            'month' => $projectionService->projectMonth($date, $this->vehicleId),
            'week' => $projectionService->projectWeek($date, $this->vehicleId),
            'day' => $projectionService->projectDay($date, $this->vehicleId),
            'agenda' => $projectionService->projectAgenda($date, 14, $this->vehicleId),
            default => $projectionService->projectMonth($date, $this->vehicleId),
        };

        $vehicles = Vehicle::orderBy('id')->get();
        $alerts = $alertService->getActiveAlerts();

        $heading = match ($this->viewMode) {
            'month' => $date->format('F Y'),
            'week' => 'Week of '.$date->copy()->startOfWeek(Carbon::MONDAY)->format('d M').' – '.$date->copy()->endOfWeek(Carbon::SUNDAY)->format('d M Y'),
            'day' => $date->format('l, d F Y'),
            'agenda' => 'Agenda: '.$date->format('d M').' – '.$date->copy()->addDays(14)->format('d M Y'),
            default => $date->format('F Y'),
        };

        return [
            'heading' => $heading,
            'items' => $items,
            'vehicles' => $vehicles,
            'alerts' => $alerts,
            'activeDateCarbon' => $date,
        ];
    }
}
