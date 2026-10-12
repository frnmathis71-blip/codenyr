<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CarbonInterface $paid_on */
class Payment extends Model
{
    public const METHODS = ['transfer' => 'Virement', 'card' => 'Carte', 'cheque' => 'Chèque', 'cash' => 'Espèces', 'other' => 'Autre'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'paid_on' => 'date', 'voided_at' => 'datetime'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
