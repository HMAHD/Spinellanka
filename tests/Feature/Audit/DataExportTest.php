<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Domain\Audit\Enums\ExportScope;
use App\Domain\Audit\Services\DataExportService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->exportService = app(DataExportService::class);
    Storage::fake('local');

    $this->complianceUser = User::create([
        'name' => 'Auditor Rohana',
        'email' => 'rohana@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->complianceUser->givePermissionTo(Permission::ExportCustomerData->value);
    $this->complianceUser->givePermissionTo(Permission::AuditExport->value);

    $this->unauthorizedUser = User::create([
        'name' => 'Field Driver Ruwan',
        'email' => 'ruwan@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);

    $vehicle = Vehicle::factory()->create();
    Booking::factory()->create([
        'reference' => 'BK-EXP-TEST-001',
        'service_type' => ServiceType::AirportPickup,
        'status' => BookingStatus::Confirmed,
        'vehicle_id' => $vehicle->id,
    ]);
});

test('generateExport enforces granular scope permissions and requires audited purpose', function (): void {
    // 1. Unauthorized staff cannot generate export
    expect(fn () => $this->exportService->generateExport(
        staff: $this->unauthorizedUser,
        scope: ExportScope::CustomerBookingHistory,
        purpose: 'Attempting download'
    ))->toThrow(AuthorizationException::class);

    // 2. Missing purpose rejected
    expect(fn () => $this->exportService->generateExport(
        staff: $this->complianceUser,
        scope: ExportScope::CustomerBookingHistory,
        purpose: '   '
    ))->toThrow(InvalidArgumentException::class, 'An audited business purpose is required');

    // 3. User with ExportCustomerData generates customer booking history export
    $export = $this->exportService->generateExport(
        staff: $this->complianceUser,
        scope: ExportScope::CustomerBookingHistory,
        purpose: 'Compliance review for external GDPR assessment'
    );

    expect($export->id)->not->toBeNull()
        ->and($export->scope)->toBe(ExportScope::CustomerBookingHistory)
        ->and($export->file_size_bytes)->toBeGreaterThan(0)
        ->and($export->isExpired())->toBeFalse()
        ->and(Storage::disk('local')->exists($export->file_path))->toBeTrue();

    // Verify CSV content
    $csv = Storage::disk('local')->get($export->file_path);
    expect($csv)->toContain('Customer Name')
        ->and($csv)->toContain('BK-EXP-TEST-001');

    // Audit event recorded
    expect(AuditEvent::where('action', 'data_export.generated')->exists())->toBeTrue();
});

test('downloadExport streams safe file response and records download tracking', function (): void {
    $export = $this->exportService->generateExport(
        staff: $this->complianceUser,
        scope: ExportScope::CustomerBookingHistory,
        purpose: 'Regulatory inspection preparation'
    );

    // Initial state: download count 0
    expect($export->download_count)->toBe(0)
        ->and($export->downloaded_at)->toBeNull();

    // Perform download via web route as authenticated compliance staff
    $response = $this->actingAs($this->complianceUser)
        ->get(route('operations.exports.download', ['uuid' => $export->export_uuid]));

    $response->assertStatus(200);
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    $response->assertHeader('Referrer-Policy', 'no-referrer');

    $export->refresh();
    expect($export->download_count)->toBe(1)
        ->and($export->downloaded_at)->not->toBeNull();
});

test('downloadExport rejects unauthorized and expired downloads', function (): void {
    $export = $this->exportService->generateExport(
        staff: $this->complianceUser,
        scope: ExportScope::CustomerBookingHistory,
        purpose: 'DSAR evidence fulfillment'
    );

    // 1. Unauthorized staff cannot download export
    $responseUnauthorized = $this->actingAs($this->unauthorizedUser)
        ->get(route('operations.exports.download', ['uuid' => $export->export_uuid]));

    $responseUnauthorized->assertStatus(403);

    // 2. Expired export (past 24 hours) is rejected
    $export->update([
        'expires_at' => Carbon::now('UTC')->subMinutes(5),
    ]);

    expect(fn () => $this->exportService->downloadExport($export, $this->complianceUser))
        ->toThrow(InvalidArgumentException::class, 'This data export has expired (24h TTL)');
});
