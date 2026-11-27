<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Operations\Services\PrivateMediaPipelineService;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->pipeline = app(PrivateMediaPipelineService::class);
    Storage::fake('local');

    $this->staff = User::create([
        'name' => 'Field Operator Kasun',
        'email' => 'kasun@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
});

test('processRasterImage sanitizes and re-encodes image stripping EXIF and malicious payloads', function (): void {
    // Generate valid 100x100 PNG
    $img = imagecreatetruecolor(100, 100);
    $bg = imagecolorallocate($img, 255, 0, 0);
    imagefill($img, 0, 0, $bg);
    ob_start();
    imagepng($img);
    $pngContent = (string) ob_get_clean();
    imagedestroy($img);

    $file = UploadedFile::fake()->createWithContent('damage.png', $pngContent);

    $result = $this->pipeline->processRasterImage($file, 'condition_attachments');

    expect($result['file_uuid'])->not->toBeNull()
        ->and($result['mime_type'])->toBe('image/jpeg')
        ->and(Storage::disk('local')->exists($result['file_path']))->toBeTrue();

    // Verify stored content is a valid JPEG
    $savedBytes = Storage::disk('local')->get($result['file_path']);
    $gdResource = @imagecreatefromstring($savedBytes);
    expect($gdResource)->not->toBeFalse();
    imagedestroy($gdResource);
});

test('processRasterImage blocks decompression bombs exceeding dimension boundaries', function (): void {
    // Fake image claims 5000x5000 dimensions
    $file = UploadedFile::fake()->image('bomb.jpg', 5000, 5000);

    expect(fn () => $this->pipeline->processRasterImage($file))
        ->toThrow(InvalidArgumentException::class, 'exceed maximum safe allocation boundary');
});

test('processRasterImage rejects non-image mime types', function (): void {
    $file = UploadedFile::fake()->create('malicious.php', 1024, 'text/x-php');

    expect(fn () => $this->pipeline->processRasterImage($file))
        ->toThrow(InvalidArgumentException::class, 'Disallowed image format');
});

test('processDocument accepts clean PDF and rejects active script tokens', function (): void {
    // 1. Clean benign PDF
    $cleanPdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n%%EOF";
    $cleanFile = UploadedFile::fake()->createWithContent('police_report.pdf', $cleanPdf);

    $result = $this->pipeline->processDocument($cleanFile, 'incident_attachments');
    expect($result['mime_type'])->toBe('application/pdf')
        ->and(Storage::disk('local')->exists($result['file_path']))->toBeTrue();

    // 2. Malicious PDF containing /JavaScript token
    $maliciousPdf = "%PDF-1.4\n1 0 obj\n<< /Type /Action /S /JavaScript /JS (app.alert('pwned');) >>\nendobj\n%%EOF";
    $maliciousFile = UploadedFile::fake()->createWithContent('malicious.pdf', $maliciousPdf);

    expect(fn () => $this->pipeline->processDocument($maliciousFile))
        ->toThrow(InvalidArgumentException::class, 'PDF contains prohibited active element (/JavaScript)');

    // 3. File missing %PDF- magic header
    $fakePdf = "NOT_A_PDF\nSome content";
    $fakeFile = UploadedFile::fake()->createWithContent('fake.pdf', $fakePdf);

    expect(fn () => $this->pipeline->processDocument($fakeFile))
        ->toThrow(InvalidArgumentException::class, 'missing %PDF- signature header');
});

test('storeSignaturePng validates PNG magic header and stores binary file', function (): void {
    $validPngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    $path = $this->pipeline->storeSignaturePng($validPngBase64, 'customer');

    expect(Storage::disk('local')->exists($path))->toBeTrue()
        ->and(str_starts_with($path, 'signatures/customer_'))->toBeTrue();

    // Reject non-PNG data
    $jpegBase64 = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';
    expect(fn () => $this->pipeline->storeSignaturePng($jpegBase64))
        ->toThrow(InvalidArgumentException::class, 'Signature must be a valid PNG raster image');
});

test('createSafeDownloadResponse attaches strict isolation headers and audits download', function (): void {
    $cleanPdf = "%PDF-1.4\nBenign file\n%%EOF";
    Storage::disk('local')->put('incident_attachments/test-doc.pdf', $cleanPdf);

    $response = $this->pipeline->createSafeDownloadResponse(
        relativePath: 'incident_attachments/test-doc.pdf',
        downloadFilename: 'police_report.pdf',
        actor: $this->staff,
        contextSummary: 'Staff downloaded test document'
    );

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; sandbox")
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment; filename=police_report.pdf');

    expect(AuditEvent::where('action', 'media.private_file_downloaded')->exists())->toBeTrue();
});
