<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $incident_id
 * @property string $action_type
 * @property string $description
 * @property int|null $performed_by_user_id
 * @property Carbon $performed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Incident $incident
 * @property-read User|null $performedByUser
 */
class IncidentAction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
