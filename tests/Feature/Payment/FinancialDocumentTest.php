<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Identity\Permission;
use App\Domain\Payment\Enums\FinancialDocumentType;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Services\FinancialDocumentService;
use App\Models\Booking;
use App\Models\FinancialDocument;
use App\Models\GuestAccessToken;
use App\Models\PaymentTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->documentService = app(FinancialDocumentService::class);
    Storage::fake('local');
});

test('receipt generation writes printable html file to private disk with checksum', function (): void {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'currency' => 'USD',
    ]);

    $transaction = PaymentTransaction::create([
        'booking_id' => $booking->id,
        'payment_method' => PaymentMethod::Cash,
        'payment_type' => PaymentType::Full,
        'amount_minor_units' => 8500,
        'currency' => 'USD',
        'status' => PaymentStatus::Completed,
        'gateway_transaction_id' => 'GW-CASH-1234',
        'idempotency_key' => 'receipt-gen-test',
    ]);

    $document = $this->documentService->generateReceipt($transaction);

    expect($document->document_type)->toBe(FinancialDocumentType::Receipt)
        ->and($document->amount_minor_units)->toBe(8500)
        ->and($document->document_number)->toStartWith('REC-'.date('Y').'-')
        ->and($document->checksum)->not->toBeEmpty();

    // Verify stored file on disk
    Storage::disk('local')->assertExists('private/'.$document->file_path);
});

test('guest can download official receipt via private token with security headers', function (): void {
    $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);

    $plainToken = 'guest-token-download-document-xyz';
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now()->addDays(7),
    ]);

    $filePath = "documents/{$booking->id}/RCP-TEST-001.html";
    Storage::disk('local')->put('private/'.$filePath, '<html><body><h1>Receipt RCP-TEST-001</h1></body></html>');

    FinancialDocument::create([
        'booking_id' => $booking->id,
        'document_type' => FinancialDocumentType::Receipt,
        'document_number' => 'RCP-TEST-001',
        'version' => 1,
        'amount_minor_units' => 5000,
        'currency' => 'USD',
        'file_path' => $filePath,
        'checksum' => 'fakechecksum123',
        'issued_at' => Carbon::now(),
    ]);

    $response = $this->get(route('booking.documents.download', [
        'reference' => $booking->reference,
        'token' => $plainToken,
        'documentNumber' => 'RCP-TEST-001',
    ]));

    $response->assertOk();
    $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $response->assertHeader('Referrer-Policy', 'no-referrer');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $response->assertSee('Receipt RCP-TEST-001');
});

test('staff with permission can view document in admin view route', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::PaymentView->value);

    $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);

    $filePath = "documents/{$booking->id}/RCP-STAFF-001.html";
    Storage::disk('local')->put('private/'.$filePath, '<html><body><h1>Staff View RCP-STAFF-001</h1></body></html>');

    FinancialDocument::create([
        'booking_id' => $booking->id,
        'document_type' => FinancialDocumentType::Receipt,
        'document_number' => 'RCP-STAFF-001',
        'version' => 1,
        'amount_minor_units' => 5000,
        'currency' => 'USD',
        'file_path' => $filePath,
        'checksum' => 'staffchecksum123',
        'issued_at' => Carbon::now(),
    ]);

    $response = $this->actingAs($staff)->get(route('staff.documents.view', [
        'booking' => $booking->id,
        'documentNumber' => 'RCP-STAFF-001',
    ]));

    $response->assertOk();
    $response->assertSee('Staff View RCP-STAFF-001');
});
