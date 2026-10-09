<?php

namespace App\Models;

use App\Services\CommercialCalculator;
use App\Services\PricingCatalog;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int, string>|null $compatible_offers
 * @property array<string, mixed>|null $contract_rules
 */
class Service extends Model
{
    public const PRICING_MODES = ['fixed' => 'Prix fixe', 'starting' => 'À partir de', 'hourly' => 'Tarif horaire', 'unit' => 'Tarif unitaire', 'monthly' => 'Tarif mensuel', 'yearly' => 'Tarif annuel', 'quote' => 'Sur devis'];

    public const PAYEES = ['codenyr' => 'Payé à Codenyr', 'client' => 'Payé directement par le client au prestataire', 'rebilled' => 'Refacturé par Codenyr'];

    public const FREQUENCIES = ['once' => 'Ponctuel', 'monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'yearly' => 'Annuel'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['contract_rules' => 'array', 'active' => 'boolean', 'amount_cents' => 'integer', 'compatible_offers' => 'array', 'archived_at' => 'datetime'];
    }

    public function priceLabel(): string
    {
        return $this->pricing_mode === 'quote' ? 'Sur devis' : ($this->pricing_mode === 'starting' ? 'À partir de ' : '').CommercialCalculator::euros($this->currentPrice());
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
