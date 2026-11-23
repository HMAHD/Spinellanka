<?php

use App\Domain\Catalog\Enums\MediaType;
use App\Domain\Catalog\Services\MediaService;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');

    $this->owner = User::create([
        'name' => 'Owner Admin',
        'email' => 'owner@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
});

test('media upload is quarantined on local disk and approval promotes to public disk', function (): void {
    $service = app(MediaService::class);
    $vehicle = Vehicle::factory()->create();

    $file = UploadedFile::fake()->image('cabin-view.jpg', 800, 600);

    // 1. Quarantine upload
    $media = $service->quarantineUpload($vehicle, $file, [
        'media_type' => MediaType::Cabin,
        'alt_text' => 'Luxury cabin leather interior',
        'sort_order' => 1,
    ], $this->owner);

    expect($media->is_approved)->toBeFalse();
    expect($media->disk)->toBe('local');
    expect(Storage::disk('local')->exists($media->file_path))->toBeTrue();
    expect(Storage::disk('public')->exists($media->file_path))->toBeFalse();

    // 2. Approve and publish
    $approvedMedia = $service->approveAndPublish($media, $this->owner);

    expect($approvedMedia->is_approved)->toBeTrue();
    expect($approvedMedia->disk)->toBe('public');
    expect(Storage::disk('public')->exists($approvedMedia->file_path))->toBeTrue();
    // Quarantined file was cleaned up from local disk
    expect(Storage::disk('local')->exists("quarantine/vehicles/{$vehicle->id}/".basename($approvedMedia->file_path)))->toBeFalse();
});

test('deleting media record removes file from disk', function (): void {
    $service = app(MediaService::class);
    $vehicle = Vehicle::factory()->create();

    $file = UploadedFile::fake()->image('exterior.jpg');
    $media = $service->quarantineUpload($vehicle, $file, [
        'media_type' => MediaType::ExteriorFront,
        'alt_text' => 'Exterior front angle',
    ], $this->owner);

    $service->approveAndPublish($media, $this->owner);
    $filePath = $media->file_path;

    expect(Storage::disk('public')->exists($filePath))->toBeTrue();

    $service->deleteMedia($media, $this->owner);

    expect(Storage::disk('public')->exists($filePath))->toBeFalse();
});
