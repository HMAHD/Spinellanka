<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Booking\Enums\ContactType;
use Carbon\Carbon;
use Database\Factories\BookingContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_id
 * @property ContactType $type
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property bool $is_whatsapp
 * @property string|null $email
 * @property bool $marketing_consent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 */
class BookingContact extends Model
{
    /** @use HasFactory<BookingContactFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'is_whatsapp' => 'boolean',
            'marketing_consent' => 'boolean',
        ];
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
