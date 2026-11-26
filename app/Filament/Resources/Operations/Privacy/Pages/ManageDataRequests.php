<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Privacy\Pages;

use App\Domain\Audit\Enums\DataRequestStatus;
use App\Domain\Audit\Services\AuditService;
use App\Filament\Resources\Operations\Privacy\DataRequestResource;
use App\Models\DataRequest;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDataRequests extends ManageRecords
{
    protected static string $resource = DataRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Log New DSAR Request')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['request_reference'] = DataRequest::generateReference();
                    $data['status'] = DataRequestStatus::PendingVerification;
                    $data['verification_expires_at'] = Carbon::now('UTC')->addHours(48);

                    return $data;
                })
                ->after(function (DataRequest $record, AuditService $auditService): void {
                    $user = auth()->user();
                    $auditService->record(
                        action: 'dsar.logged',
                        actor: $user,
                        summary: "New DSAR request {$record->request_reference} logged for {$record->subject_email} by {$user?->name}",
                        metadata: [
                            'request_reference' => $record->request_reference,
                            'request_type' => $record->request_type->value,
                            'subject_email' => $record->subject_email,
                        ],
                        auditable: $record
                    );
                }),
        ];
    }
}
