<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Audit\Enums\DataRequestStatus;
use App\Domain\Audit\Enums\DataRequestType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $request_reference
 * @property DataRequestType $request_type
 * @property string $subject_email
 * @property string|null $subject_phone
 * @property string|null $subject_name
 * @property string|null $verification_token_hash
 * @property Carbon|null $verification_expires_at
 * @property DataRequestStatus $status
 * @property Carbon|null $verified_at
 * @property Carbon|null $completed_at
 * @property string|null $notes
 * @property int|null $processed_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $processedByUser
 */
class DataRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'request_type' => DataRequestType::class,
            'status' => DataRequestStatus::class,
            'verification_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function generateReference(): string
    {
        $date = Carbon::now('Asia/Colombo')->format('Ymd');
        $random = strtoupper(bin2hex(random_bytes(3)));

        return sprintf('REQ-%s-%s', $date, $random);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }
}
