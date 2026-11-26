<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Audit\Enums\ExportScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $export_uuid
 * @property int $requested_by_user_id
 * @property string $purpose
 * @property ExportScope $scope
 * @property string $file_path
 * @property int $file_size_bytes
 * @property Carbon $expires_at
 * @property Carbon|null $downloaded_at
 * @property int $download_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $requestedByUser
 */
class DataExport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scope' => ExportScope::class,
            'file_size_bytes' => 'integer',
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'download_count' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return Carbon::now('UTC')->isAfter($this->expires_at);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
