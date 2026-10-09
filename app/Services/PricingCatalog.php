<?php

namespace App\Services;

use App\Models\Price;

class PricingCatalog
{
    /** @return array<string, array<string, mixed>> */
    public function offers(): array
    {
        return $this->withPrices(config('codenyr.offers'), 'offer', $this->storedPrices());
    }

    /** @return array<string, array<string, mixed>> */
    public function maintenance(): array
    {
        return $this->withPrices(config('codenyr.maintenance'), 'maintenance', $this->storedPrices());
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $items = [];
        $stored = $this->storedPrices();
        foreach (['offer' => 'offers', 'maintenance' => 'maintenance'] as $group => $configuration) {
            $plans = $this->withPrices(config('codenyr.'.$configuration), $group, $stored);
            foreach ($plans as $key => $plan) {
                $items[$group.'_'.$key] = [...$plan, 'group' => $group];
            }
        }

        return $items;
    }

    /** @return array<string, int> */
    private function storedPrices(): array
    {
        return Price::pluck('amount_cents', 'key')->all();
    }

    /** @param array<string, array<string, mixed>> $plans
     * @param  array<string, int>  $stored
     * @return array<string, array<string, mixed>>
     */
    private function withPrices(array $plans, string $group, array $stored): array
    {
        foreach ($plans as $key => &$plan) {
            $cents = $stored[$group.'_'.$key] ?? CommercialCalculator::decimal($plan['price']);
            $plan['amount_cents'] = $cents;
            $plan['price'] = number_format($cents / 100, $group === 'maintenance' || $cents % 100 !== 0 ? 2 : 0, ',', ' ');
        }

        return $plans;
    }
}
