<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Audit\Services\AuditService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PrivateMediaPipelineService
{
    public const MAX_RASTER_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

    public const MAX_DOCUMENT_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    public const MAX_DIMENSION_PX = 4096;

    public const MAX_PIXEL_AREA = 16_000_000; // 16 megapixels

    public function __construct(
        protected AuditService $auditService,
    ) {}

    /**
     * Process and sanitize an image upload via strict GD decode and re-encode.
     * Strips all EXIF, malicious payload tails, and polyglots.
     *
     * @return array{file_uuid: string, file_path: string, original_filename: string, mime_type: string, file_size_bytes: int}
     */
    public function processRasterImage(UploadedFile $file, string $targetDirectory = 'condition_attachments'): array
    {
        if ($file->getSize() > self::MAX_RASTER_SIZE_BYTES) {
            throw new InvalidArgumentException(sprintf('Image size %d bytes exceeds maximum limit of 5MB.', $file->getSize()));
        }

        $realPath = $file->getRealPath();
        $detectedMime = $file->getMimeType();
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (! in_array($detectedMime, $allowedMimes, true)) {
            throw new InvalidArgumentException(sprintf('Disallowed image format: %s. Only JPEG, PNG, and WebP are permitted.', $detectedMime));
        }

        // 1. Dimension validation before memory allocation (prevents decompression bombs)
        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false) {
            throw new InvalidArgumentException('Malformed or unreadable image file.');
        }

        [$width, $height] = $imageInfo;
        if ($width > self::MAX_DIMENSION_PX || $height > self::MAX_DIMENSION_PX || ($width * $height) > self::MAX_PIXEL_AREA) {
            throw new InvalidArgumentException(sprintf('Image dimensions %dx%d exceed maximum safe allocation boundary.', $width, $height));
        }

        // 2. Decode into GD image resource
        $fileContents = file_get_contents($realPath);
        if ($fileContents === false) {
            throw new InvalidArgumentException('Unable to read upload stream.');
        }

        $image = @imagecreatefromstring($fileContents);
        if ($image === false) {
            throw new InvalidArgumentException('Image decoding failed; file contains invalid raster data.');
        }

        // 3. Handle EXIF orientation rotation if present
        if ($detectedMime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($realPath);
            if (! empty($exif['Orientation'])) {
                $image = match ((int) $exif['Orientation']) {
                    3 => imagerotate($image, 180, 0),
                    6 => imagerotate($image, -90, 0),
                    8 => imagerotate($image, 90, 0),
                    default => $image,
                };
            }
        }

        // 4. Re-encode to clean JPEG buffer (strips all EXIF, script tails, and polyglots)
        ob_start();
        imagejpeg($image, null, 85);
        $cleanBytes = (string) ob_get_clean();
        imagedestroy($image);

        // 5. Store on private disk
        $uuid = (string) Str::uuid();
        $relativePath = sprintf('%s/%s.jpg', trim($targetDirectory, '/'), $uuid);

        Storage::disk('local')->put($relativePath, $cleanBytes);

        return [
            'file_uuid' => $uuid,
            'file_path' => $relativePath,
            'original_filename' => basename($file->getClientOriginalName()),
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => strlen($cleanBytes),
        ];
    }

    /**
     * Process an incident PDF document (e.g. Police Report, Insurance Claim).
     *
     * @return array{file_uuid: string, file_path: string, original_filename: string, mime_type: string, file_size_bytes: int}
     */
    public function processDocument(UploadedFile $file, string $targetDirectory = 'incident_attachments'): array
    {
        if ($file->getSize() > self::MAX_DOCUMENT_SIZE_BYTES) {
            throw new InvalidArgumentException(sprintf('Document size %d bytes exceeds maximum limit of 10MB.', $file->getSize()));
        }

        $realPath = $file->getRealPath();
        $content = file_get_contents($realPath);
        if ($content === false) {
            throw new InvalidArgumentException('Unable to read document stream.');
        }

        // 1. Verify %PDF- magic bytes header
        if (! str_starts_with($content, '%PDF-')) {
            throw new InvalidArgumentException('Invalid document format; file is missing %PDF- signature header.');
        }

        // 2. Scan for malicious executable PDF objects
        $prohibitedTokens = ['/JavaScript', '/JS', '/Launch', '/Action', '/EmbeddedFiles'];
        foreach ($prohibitedTokens as $token) {
            if (stripos($content, $token) !== false) {
                throw new InvalidArgumentException(sprintf('Security violation: PDF contains prohibited active element (%s).', $token));
            }
        }

        $uuid = (string) Str::uuid();
        $relativePath = sprintf('%s/%s.pdf', trim($targetDirectory, '/'), $uuid);

        Storage::disk('local')->put($relativePath, $content);

        return [
            'file_uuid' => $uuid,
            'file_path' => $relativePath,
            'original_filename' => basename($file->getClientOriginalName()),
            'mime_type' => 'application/pdf',
            'file_size_bytes' => strlen($content),
        ];
    }

    /**
     * Store a base64 encoded PNG signature securely as a clean binary file.
     */
    public function storeSignaturePng(string $base64Data, string $prefix = 'sig'): string
    {
        // Strip data URI scheme prefix if present
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        }

        $decoded = base64_decode($base64Data, true);
        if ($decoded === false || strlen($decoded) < 32) {
            throw new InvalidArgumentException('Malformed or empty signature data.');
        }

        // Check PNG magic header bytes (\x89PNG\r\n\x1a\n)
        $pngHeader = "\x89PNG\r\n\x1a\n";
        if (! str_starts_with($decoded, $pngHeader)) {
            throw new InvalidArgumentException('Signature must be a valid PNG raster image.');
        }

        $uuid = (string) Str::uuid();
        $relativePath = sprintf('signatures/%s_%s.png', $prefix, $uuid);

        Storage::disk('local')->put($relativePath, $decoded);

        return $relativePath;
    }

    /**
     * Create safe binary file download response with strict isolation security headers.
     */
    public function createSafeDownloadResponse(
        string $relativePath,
        string $downloadFilename,
        ?User $actor = null,
        ?string $contextSummary = null
    ): BinaryFileResponse {
        $disk = Storage::disk('local');
        if (! $disk->exists($relativePath)) {
            abort(404, 'Requested attachment not found.');
        }

        $fullPath = $disk->path($relativePath);
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $downloadFilename) ?: 'attachment.bin';

        if ($actor !== null) {
            $this->auditService->record(
                action: 'media.private_file_downloaded',
                actor: $actor,
                summary: $contextSummary ?: "Staff downloaded private attachment: {$safeFilename}",
                metadata: [
                    'relative_path' => $relativePath,
                    'filename' => $safeFilename,
                    'file_size' => filesize($fullPath),
                ]
            );
        }

        $response = response()->download($fullPath, $safeFilename, [
            'Content-Type' => 'application/octet-stream',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Referrer-Policy' => 'no-referrer',
        ]);

        return $response;
    }
}
