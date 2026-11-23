<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Catalog\Enums\MediaType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class MediaService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function quarantineUpload(Vehicle $vehicle, UploadedFile $file, array $metadata, ?User $actor = null): VehicleMedia
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            throw new InvalidArgumentException("Invalid file type: {$file->getMimeType()}");
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('File size exceeds 5MB limit.');
        }

        $hash = hash_file('sha256', (string) $file->getRealPath());
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $quarantinePath = "quarantine/vehicles/{$vehicle->id}/{$hash}.{$ext}";

        Storage::disk('local')->putFileAs("quarantine/vehicles/{$vehicle->id}", $file, "{$hash}.{$ext}");

        $mediaType = $metadata['media_type'] instanceof MediaType
            ? $metadata['media_type']
            : MediaType::from((string) $metadata['media_type']);

        $media = VehicleMedia::create([
            'vehicle_id' => $vehicle->id,
            'file_path' => $quarantinePath,
            'disk' => 'local',
            'media_type' => $mediaType,
            'alt_text' => (string) ($metadata['alt_text'] ?? ''),
            'photographer' => isset($metadata['photographer']) ? (string) $metadata['photographer'] : null,
            'usage_rights' => isset($metadata['usage_rights']) ? (string) $metadata['usage_rights'] : null,
            'sort_order' => isset($metadata['sort_order']) ? (int) $metadata['sort_order'] : 0,
            'is_approved' => false,
        ]);

        $this->auditService->record(
            action: 'fleet.media.uploaded',
            actor: $actor,
            summary: "Uploaded media {$media->id} for vehicle {$vehicle->public_name} (Quarantined)",
            metadata: ['media_id' => $media->id, 'vehicle_id' => $vehicle->id, 'media_type' => $mediaType->value],
            auditable: $media
        );

        return $media;
    }

    public function approveAndPublish(VehicleMedia $media, ?User $actor = null): VehicleMedia
    {
        if ($media->is_approved && $media->disk === 'public') {
            return $media;
        }

        $quarantineDisk = Storage::disk($media->disk);
        if ($quarantineDisk->exists($media->file_path)) {
            $contents = $quarantineDisk->get($media->file_path);
            $filename = basename($media->file_path);
            $publicPath = "vehicles/{$media->vehicle_id}/media/{$filename}";

            // Store on public disk
            Storage::disk('public')->put($publicPath, (string) $contents);

            // Delete quarantined file
            $quarantineDisk->delete($media->file_path);

            $media->update([
                'file_path' => $publicPath,
                'disk' => 'public',
                'is_approved' => true,
            ]);
        } else {
            $media->update([
                'is_approved' => true,
            ]);
        }

        $vehicle = $media->vehicle;
        $vehicleName = $vehicle instanceof Vehicle ? $vehicle->public_name : "Vehicle #{$media->vehicle_id}";

        $this->auditService->record(
            action: 'fleet.media.approved',
            actor: $actor,
            summary: "Approved media {$media->id} for vehicle {$vehicleName}",
            metadata: ['media_id' => $media->id, 'public_path' => $media->file_path],
            auditable: $media
        );

        return $media;
    }

    public function deleteMedia(VehicleMedia $media, ?User $actor = null): void
    {
        if (Storage::disk($media->disk)->exists($media->file_path)) {
            Storage::disk($media->disk)->delete($media->file_path);
        }

        $vehicle = $media->vehicle;
        $mediaId = $media->id;
        $vehicleName = $vehicle instanceof Vehicle ? $vehicle->public_name : "Vehicle #{$media->vehicle_id}";

        $media->delete();

        $this->auditService->record(
            action: 'fleet.media.deleted',
            actor: $actor,
            summary: "Deleted media {$mediaId} for vehicle {$vehicleName}",
            metadata: ['media_id' => $mediaId, 'vehicle_id' => $media->vehicle_id],
            auditable: $vehicle instanceof Vehicle ? $vehicle : null
        );
    }
}
