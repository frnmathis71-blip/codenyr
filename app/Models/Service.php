<?php

namespace App\Models;

use App\Services\PricingCatalog;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    public const FREQUENCIES = ['once' => 'Ponctuel', 'monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'yearly' => 'Annuel'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'amount_cents' => 'integer'];
    }

    public function currentPrice(): int
    {
        if ($this->price_key) {
            $plan = app(PricingCatalog::class)->all()[$this->price_key] ?? null;
            if ($plan) {
                return $plan['amount_cents'];
            }
        }

        return $this->amount_cents;
    }
}
