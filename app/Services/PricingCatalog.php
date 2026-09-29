<?php

namespace App\Services;

use App\Models\Price;

class PricingCatalog
{
    /** @return array<string, array<string, mixed>> */
    public function offers(): array
    {
        return $this->withPrices(config('codenyr.offers'), 'offer');
    }

    /** @return array<string, array<string, mixed>> */
    public function maintenance(): array
    {
        return $this->withPrices(config('codenyr.maintenance'), 'maintenance');
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $items = [];
        foreach (['offer' => $this->offers(), 'maintenance' => $this->maintenance()] as $group => $plans) {
            foreach ($plans as $key => $plan) {
                $items[$group.'_'.$key] = [...$plan, 'group' => $group];
            }
        }

        return $items;
    }

    /** @param array<string, array<string, mixed>> $plans
     * @return array<string, array<string, mixed>>
     */
    private function withPrices(array $plans, string $group): array
    {
        $stored = Price::pluck('amount_cents', 'key');
        foreach ($plans as $key => &$plan) {
            $cents = $stored[$group.'_'.$key] ?? (int) round((float) str_replace([' ', ','], ['', '.'], $plan['price']) * 100);
            $plan['amount_cents'] = $cents;
            $plan['price'] = number_format($cents / 100, $group === 'maintenance' || $cents % 100 !== 0 ? 2 : 0, ',', ' ');
        }

        return $plans;
    }
}
