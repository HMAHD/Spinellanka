<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Privacy\Pages;

use App\Domain\Audit\Enums\ExportScope;
use App\Domain\Audit\Services\DataExportService;
use App\Domain\Identity\Permission;
use App\Filament\Resources\Operations\Privacy\DataExportResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageDataExports extends ManageRecords
{
    protected static string $resource = DataExportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_export')
                ->label('Generate Data Export')
                ->icon('heroicon-o-plus')
                ->modalHeading('Generate Audited Data Export')
                ->modalDescription('Generates a point-in-time CSV export stored in private encrypted quarantine with a strict 24-hour expiration.')
                ->form([
                    Select::make('scope')
                        ->label('Export Scope')
                        ->options(function (): array {
                            /** @var User|null $user */
                            $user = auth()->user();
                            $options = [];

                            if ($user?->can(Permission::ExportCustomerData->value) || $user?->hasPermissionTo(Permission::ExportCustomerData->value)) {
                                $options[ExportScope::CustomerBookingHistory->value] = ExportScope::CustomerBookingHistory->label().' (Requires export.customer_data)';
                            }

                            if ($user?->can(Permission::ExportFinancialData->value) || $user?->hasPermissionTo(Permission::ExportFinancialData->value)) {
                                $options[ExportScope::FinancialRecords->value] = ExportScope::FinancialRecords->label().' (Requires export.financial_data)';
                            }

                            if ($user?->can(Permission::AuditExport->value) || $user?->hasPermissionTo(Permission::AuditExport->value)) {
                                $options[ExportScope::OperationsAudit->value] = ExportScope::OperationsAudit->label().' (Requires audit.export)';
                            }

                            return $options;
                        })
                        ->required(),

                    Textarea::make('purpose')
                        ->label('Compliance Justification / Purpose')
                        ->required()
                        ->minLength(10)
                        ->helperText('State the specific business justification, regulatory ticket, or customer DSAR reference.'),
                ])
                ->action(function (array $data, DataExportService $exportService): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $scope = ExportScope::from((string) $data['scope']);
                    $purpose = (string) $data['purpose'];

                    $export = $exportService->generateExport($user, $scope, $purpose);

                    Notification::make()
                        ->title('Export Generated Successfully')
                        ->body(sprintf('Export UUID %s created. Available for download for 24 hours.', $export->export_uuid))
                        ->success()
                        ->send();
                }),
        ];
    }
}
