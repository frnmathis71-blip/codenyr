<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $client_project_id
 * @property string|null $number
 * @property string $status
 * @property CarbonInterface $issued_on
 * @property CarbonInterface $due_on
 * @property CarbonInterface|null $finalized_at
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface|null $archived_at
 * @property int $discount_value
 * @property int $deposit_value
 * @property string $discount_type
 * @property string $deposit_type
 * @property string|null $conditions
 * @property string|null $estimated_delay
 * @property array<int, array<string, mixed>> $items
 * @property array<string, mixed> $snapshot
 * @property array<string, mixed> $totals
 */
abstract class BillingRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['items' => 'array', 'snapshot' => 'array', 'totals' => 'array', 'discount_value' => 'integer', 'deposit_value' => 'integer', 'issued_on' => 'date', 'due_on' => 'date', 'finalized_at' => 'datetime', 'sent_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    /** @return BelongsTo<ClientProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    protected static function booted(): void
    {
        static::updating(function (BillingRecord $record): void {
            if ($record->getRawOriginal('finalized_at') !== null) {
                foreach ($record->getDirty() as $key => $value) {
                    if (! in_array($key, ['archived_at', 'updated_at', 'status', 'sent_at'], true)) {
                        throw new LogicException('Ce document finalisé est immuable.');
                    }
                }
                if ($record instanceof Invoice && $record->isDirty('status')) {
                    throw new LogicException('Le règlement est calculé depuis les paiements.');
                }
            }
        });
        static::deleting(function (BillingRecord $record): void {
            if ($record->finalized_at !== null || $record->number !== null) {
                throw new LogicException('Archivez les documents numérotés.');
            }
        });
    }
}
