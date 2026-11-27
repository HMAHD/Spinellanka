<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configuration\Enums\PackageClassification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $version_number
 * @property string $schema_version
 * @property PackageClassification $package_classification
 * @property string $source_revision
 * @property string $checksum_sha256
 * @property array<string, int> $record_counts
 * @property string $payload_json
 * @property bool $is_active
 * @property int|null $applied_by_user_id
 * @property Carbon|null $applied_at
 * @property int|null $rolled_back_from_id
 * @property string|null $rollback_reason
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $appliedBy
 * @property-read ConfigurationSnapshot|null $rolledBackFrom
 */
class ConfigurationSnapshot extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'is_active' => false,
        'applied_by_user_id' => null,
        'applied_at' => null,
        'rolled_back_from_id' => null,
        'rollback_reason' => null,
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'package_classification' => PackageClassification::class,
            'record_counts' => 'array',
            'is_active' => 'boolean',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    /**
     * @return BelongsTo<ConfigurationSnapshot, $this>
     */
    public function rolledBackFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rolled_back_from_id');
    }

    /**
     * Decode the persisted canonical JSON package envelope.
     *
     * @return array<string, mixed>
     */
    public function decodedPackage(): array
    {
        $decoded = json_decode($this->payload_json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
