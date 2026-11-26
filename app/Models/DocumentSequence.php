<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Payment\Enums\FinancialDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $document_type
 * @property int $year
 * @property int $last_number
 */
class DocumentSequence extends Model
{
    protected $guarded = ['id'];

    /**
     * Atomically allocate the next formatted document number (e.g. REC-2026-00001).
     */
    public static function nextNumber(FinancialDocumentType $type, int $year): string
    {
        return DB::transaction(function () use ($type, $year) {
            /** @var DocumentSequence $sequence */
            $sequence = self::where('document_type', $type->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = self::create([
                    'document_type' => $type->value,
                    'year' => $year,
                    'last_number' => 1,
                ]);
                $next = 1;
            } else {
                $next = $sequence->last_number + 1;
                $sequence->update(['last_number' => $next]);
            }

            return sprintf('%s-%d-%05d', $type->prefix(), $year, $next);
        });
    }
}
