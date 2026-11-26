<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\IncidentAttachmentCategory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $incident_id
 * @property IncidentAttachmentCategory $category
 * @property string $file_uuid
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $file_size_bytes
 * @property string|null $caption
 * @property string $processing_status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Incident $incident
 */
class IncidentAttachment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'category' => IncidentAttachmentCategory::class,
            'file_size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
