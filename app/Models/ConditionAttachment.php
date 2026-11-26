<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\ConditionAttachmentCategory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vehicle_condition_record_id
 * @property ConditionAttachmentCategory $category
 * @property string $file_uuid
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $file_size_bytes
 * @property string|null $caption
 * @property string $processing_status
 * @property Carbon $captured_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read VehicleConditionRecord $conditionRecord
 */
class ConditionAttachment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'category' => ConditionAttachmentCategory::class,
            'file_size_bytes' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<VehicleConditionRecord, $this>
     */
    public function conditionRecord(): BelongsTo
    {
        return $this->belongsTo(VehicleConditionRecord::class, 'vehicle_condition_record_id');
    }
}
