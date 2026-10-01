<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $quote_id
 * @property string $invoice_type
 */
class Invoice extends BillingRecord
{
    public const TYPES = ['standard' => 'Classique', 'deposit' => 'Acompte', 'interim' => 'Intermédiaire', 'balance' => 'Solde'];

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paidCents(): int
    {
        return (int) $this->payments->whereNull('voided_at')->sum('amount_cents');
    }

    public function remainingCents(): int
    {
        return max(0, (int) ($this->totals['initial']['ttc'] ?? 0) - $this->paidCents());
    }

    public function paymentLabel(): string
    {
        return ! $this->finalized_at ? 'Brouillon' : ($this->remainingCents() === 0 ? 'Payée' : ($this->paidCents() > 0 ? 'Partiellement payée' : 'À régler'));
    }
}
