<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Enums\ExportScope;
use App\Domain\Operations\Services\PrivateMediaPipelineService;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\DataExport;
use App\Models\PaymentTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataExportService
{
    public function __construct(
        protected PrivateMediaPipelineService $mediaPipeline,
        protected AuditService $auditService,
    ) {}

    /**
     * Generate an encrypted/private scoped CSV export file.
     */
    public function generateExport(User $staff, ExportScope $scope, string $purpose): DataExport
    {
        $requiredPermission = $scope->requiredPermission();
        if (! $staff->can($requiredPermission) && ! $staff->hasPermissionTo($requiredPermission)) {
            throw new AuthorizationException(sprintf('Actor does not have %s permission for export scope %s.', $requiredPermission, $scope->value));
        }

        if (trim($purpose) === '') {
            throw new InvalidArgumentException('An audited business purpose is required to generate a data export.');
        }

        $csvContent = match ($scope) {
            ExportScope::CustomerBookingHistory => $this->buildCustomerBookingHistoryCsv(),
            ExportScope::FinancialRecords => $this->buildFinancialRecordsCsv(),
            ExportScope::OperationsAudit => $this->buildOperationsAuditCsv(),
        };

        $uuid = (string) Str::uuid();
        $relativePath = sprintf('exports/%s.csv', $uuid);

        Storage::disk('local')->put($relativePath, $csvContent);

        /** @var DataExport $export */
        $export = DataExport::create([
            'export_uuid' => $uuid,
            'requested_by_user_id' => $staff->id,
            'purpose' => $purpose,
            'scope' => $scope,
            'file_path' => $relativePath,
            'file_size_bytes' => strlen($csvContent),
            'expires_at' => Carbon::now('UTC')->addHours(24),
            'download_count' => 0,
        ]);

        $this->auditService->record(
            action: 'data_export.generated',
            actor: $staff,
            summary: "Staff {$staff->name} generated {$scope->value} data export ({$export->export_uuid}) for purpose: {$purpose}",
            metadata: [
                'export_uuid' => $export->export_uuid,
                'scope' => $scope->value,
                'purpose' => $purpose,
                'file_size_bytes' => $export->file_size_bytes,
                'expires_at' => $export->expires_at->toIso8601String(),
            ],
            auditable: $export
        );

        return $export;
    }

    /**
     * Stream safe download response with security headers and increment download tracking.
     */
    public function downloadExport(DataExport $export, User $staff): BinaryFileResponse
    {
        $requiredPermission = $export->scope->requiredPermission();
        if (! $staff->can($requiredPermission) && ! $staff->hasPermissionTo($requiredPermission)) {
            throw new AuthorizationException(sprintf('Actor does not have %s permission to download export.', $requiredPermission));
        }

        if ($export->isExpired()) {
            throw new InvalidArgumentException('This data export has expired (24h TTL) and is no longer accessible.');
        }

        $export->increment('download_count');
        $export->update(['downloaded_at' => Carbon::now('UTC')]);

        return $this->mediaPipeline->createSafeDownloadResponse(
            relativePath: $export->file_path,
            downloadFilename: sprintf('%s_%s.csv', $export->scope->value, Carbon::now('Asia/Colombo')->format('Ymd_His')),
            actor: $staff,
            contextSummary: "Staff {$staff->name} downloaded data export {$export->export_uuid} ({$export->scope->value})"
        );
    }

    protected function buildCustomerBookingHistoryCsv(): string
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Booking Reference', 'Service Type', 'Status', 'Customer Name', 'Email', 'Phone', 'Created At']);

        $bookings = Booking::with('contacts')->latest('id')->take(1000)->get();
        foreach ($bookings as $b) {
            $leadContact = $b->contacts->first();
            $customerName = $leadContact ? sprintf('%s %s', $leadContact->first_name, $leadContact->last_name) : 'N/A';
            fputcsv($output, [
                $b->reference,
                $b->service_type->value,
                $b->status->value,
                $customerName,
                $leadContact ? ($leadContact->email ?? 'N/A') : 'N/A',
                $leadContact ? $leadContact->phone : 'N/A',
                $b->created_at->toIso8601String(),
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return (string) $content;
    }

    protected function buildFinancialRecordsCsv(): string
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Transaction Reference', 'Booking Reference', 'Method', 'Type', 'Status', 'Amount Minor Units', 'Currency', 'Settled At']);

        $transactions = PaymentTransaction::with('booking')->latest('id')->take(1000)->get();
        foreach ($transactions as $txn) {
            fputcsv($output, [
                $txn->reference,
                $txn->booking ? $txn->booking->reference : 'N/A',
                $txn->payment_method->value,
                $txn->payment_type->value,
                $txn->status->value,
                $txn->amount_minor_units,
                $txn->currency,
                $txn->settled_at?->toIso8601String() ?? 'N/A',
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return (string) $content;
    }

    protected function buildOperationsAuditCsv(): string
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Audit ID', 'Action', 'Actor Name', 'Actor Type', 'Summary', 'IP Address', 'Timestamp']);

        $events = AuditEvent::latest('id')->take(1000)->get();
        foreach ($events as $evt) {
            fputcsv($output, [
                $evt->id,
                $evt->action,
                $evt->actor_name ?? 'System',
                $evt->actor_type,
                $evt->summary,
                $evt->ip_address ?? 'N/A',
                Carbon::parse((string) $evt->created_at)->toIso8601String(),
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return (string) $content;
    }
}
