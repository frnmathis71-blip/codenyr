<?php

namespace App\Services;

use App\Models\Service;

class CommercialCatalog
{
    public function initialize(): void
    {
        foreach (app(PricingCatalog::class)->all() as $key => $plan) {
            Service::firstOrCreate(['price_key' => $key], ['category' => $plan['group'] === 'offer' ? 'Création' : 'Maintenance', 'name' => $plan['name'], 'description' => $plan['description'] ?? $plan['intro'], 'amount_cents' => $plan['amount_cents'], 'frequency' => $plan['group'] === 'offer' ? 'once' : 'monthly']);
        }
    }
}
