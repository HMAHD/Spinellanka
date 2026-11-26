<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property bool $signature_valid
 * @property string|null $source_ip
 * @property array<string, mixed>|null $payload_summary
 * @property Carbon $received_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebhookReceipt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload_summary' => 'array',
            'received_at' => 'datetime',
        ];
    }
}
