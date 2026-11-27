<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Domain\Audit\Enums\ExportScope;
use App\Domain\Audit\Services\PrivacyRetentionService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ContactType;
use App\Domain\Operations\Enums\DriverTripStatus;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingHold;
use App\Models\DataExport;
use App\Models\Driver;
use App\Models\DriverAccessToken;
use App\Models\DriverAssignment;
use App\Models\GuestAccessToken;
use App\Models\LegalHold;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->retentionService = app(PrivacyRetentionService::class);
    Storage::fake('local');

    $this->staff = User::create([
        'name' => 'Compliance Officer Nimal',
        'email' => 'nimal@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
});

test('retention pass purges expired guest tokens, driver tokens, and stale booking holds', function (): void {
    $now = Carbon::now('UTC');

    $vehicle = Vehicle::factory()->create();
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'vehicle_id' => $vehicle->id,
    ]);

    // 1. Guest access tokens: one expired 35 days ago (should be purged), one expired 5 days ago (should remain)
    $staleGuestToken = GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => hash('sha256', 'stale_token'),
        'purpose' => 'status',
        'expires_at' => $now->copy()->subDays(35),
    ]);
    $recentGuestToken = GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => hash('sha256', 'recent_token'),
        'purpose' => 'status',
        'expires_at' => $now->copy()->subDays(5),
    ]);

    // 2. Driver access tokens: one expired 40 days ago (should be purged)
    $driver = Driver::factory()->create();
    $assignment = DriverAssignment::create([
        'booking_id' => $booking->id,
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'assigned_by_user_id' => $this->staff->id,
        'assigned_at' => $now->copy()->subDays(45),
        'status' => DriverTripStatus::Assigned,
    ]);
    $staleDriverToken = DriverAccessToken::create([
        'booking_id' => $booking->id,
        'driver_assignment_id' => $assignment->id,
        'driver_id' => $driver->id,
        'token_hash' => hash('sha256', 'stale_driver'),
        'expires_at' => $now->copy()->subDays(40),
    ]);

    // 3. Booking holds: one expired 10 days ago (should be purged), one expired 2 days ago (should remain)
    $staleHold = BookingHold::factory()->create([
        'expires_at' => $now->copy()->subDays(10),
    ]);
    $recentHold = BookingHold::factory()->create([
        'expires_at' => $now->copy()->subDays(2),
    ]);

    // 4. Data export expired 2 days ago
    Storage::disk('local')->put('exports/test-expired.csv', 'dummy,csv,data');
    $staleExport = DataExport::create([
        'export_uuid' => (string) Str::uuid(),
        'requested_by_user_id' => $this->staff->id,
        'purpose' => 'Annual audit export',
        'scope' => ExportScope::OperationsAudit,
        'file_path' => 'exports/test-expired.csv',
        'file_size_bytes' => 14,
        'expires_at' => $now->copy()->subDays(2),
    ]);

    $stats = $this->retentionService->executeRetentionPass();

    expect($stats['purged_tokens_count'])->toBe(2)
        ->and($stats['purged_holds_count'])->toBe(1)
        ->and($stats['purged_exports_count'])->toBe(1);

    expect(GuestAccessToken::find($staleGuestToken->id))->toBeNull()
        ->and(GuestAccessToken::find($recentGuestToken->id))->not->toBeNull()
        ->and(DriverAccessToken::find($staleDriverToken->id))->toBeNull()
        ->and(BookingHold::find($staleHold->id))->toBeNull()
        ->and(BookingHold::find($recentHold->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists('exports/test-expired.csv'))->toBeFalse();

    $staleExport->refresh();
    expect($staleExport->file_path)->toBe('purged');
});

test('retention pass preserves bookings with active legal holds and anonymizes after hold release', function (): void {
    $now = Carbon::now('UTC');
    $agedDate = $now->copy()->subYears(4);

    $vehicle = Vehicle::factory()->create();
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Completed,
        'completed_at' => $agedDate,
        'vehicle_id' => $vehicle->id,
        'anonymized_at' => null,
    ]);

    $contact = BookingContact::create([
        'booking_id' => $booking->id,
        'type' => ContactType::LeadTraveler,
        'first_name' => 'Alice',
        'last_name' => 'Wonderland',
        'email' => 'alice@example.test',
        'phone' => '+447911123456',
        'is_whatsapp' => true,
    ]);

    // Apply active legal hold
    $legalHold = LegalHold::create([
        'booking_id' => $booking->id,
        'reason' => 'Pending litigation on highway incident',
        'details' => 'Western Province High Court CASE-99',
        'applied_by_user_id' => $this->staff->id,
        'applied_at' => Carbon::now('UTC'),
        'is_active' => true,
    ]);

    // Pass 1: Active legal hold must protect booking from anonymization
    $stats = $this->retentionService->executeRetentionPass();
    expect($stats['anonymized_bookings_count'])->toBe(0);

    $booking->refresh();
    $contact->refresh();
    expect($booking->anonymized_at)->toBeNull()
        ->and($contact->first_name)->toBe('Alice')
        ->and($contact->email)->toBe('alice@example.test');

    // Release legal hold
    $legalHold->update([
        'is_active' => false,
        'released_by_user_id' => $this->staff->id,
        'released_at' => Carbon::now('UTC'),
        'release_reason' => 'Court proceedings formally concluded with verdict',
    ]);

    // Pass 2: With legal hold released, retention pass anonymizes the aged booking
    $statsPass2 = $this->retentionService->executeRetentionPass();
    expect($statsPass2['anonymized_bookings_count'])->toBe(1);

    $booking->refresh();
    $contact->refresh();
    expect($booking->anonymized_at)->not->toBeNull()
        ->and($contact->first_name)->toBe('Anonymized')
        ->and($contact->last_name)->toBe('Traveler')
        ->and($contact->phone)->toBe('[REDACTED]')
        ->and($contact->is_whatsapp)->toBeFalse()
        ->and($contact->email)->toContain('@redacted.invalid');
});

test('retention artisan command executes retention pass successfully', function (): void {
    $exitCode = Artisan::call('retention:execute');
    expect($exitCode)->toBe(0);
});
