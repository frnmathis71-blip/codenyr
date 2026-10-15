<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * Data for the public budget estimator, built from the admin catalogue so the
 * estimate always follows the prices actually used on quotes.
 */
class PriceEstimator
{
    /** Pages or sections already included in each offer. */
    public const INCLUDED = [
        'landing' => ['count' => 6, 'unit' => 'section'],
        'vitrine' => ['count' => 5, 'unit' => 'page'],
        'pro' => ['count' => 10, 'unit' => 'page'],
    ];

    /** Catalogue entries that are never part of a first-project estimate. */
    private const EXCLUDED_CATEGORIES = ['Création', 'Maintenance', 'Intervention', 'Frais tiers'];

    /** Upper bound of the indicative range, as a share of the computed total. */
    public const MARGIN = 0.2;

    public function __construct(private PricingCatalog $catalog) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        $services = Service::where('active', true)->whereNull('archived_at')->orderBy('position')->orderBy('id')->get();
        $offers = [];
        foreach ($this->catalog->offers() as $key => $offer) {
            $included = self::INCLUDED[$key] ?? null;
            $extra = $included ? $services->first(fn (Service $service) => $service->pricing_mode === 'unit'
                && $service->unit === $included['unit'] && $this->compatible($service, $key)) : null;
            $offers[$key] = [
                'name' => $offer['name'],
                'intro' => $offer['intro'],
                'amount' => $offer['amount_cents'],
                'included' => $included['count'] ?? null,
                'unit' => $included['unit'] ?? null,
                'extra' => $extra ? $extra->currentPrice() : null,
                'options' => $this->options($services, $key)->pluck('id')->all(),
            ];
        }

        return [
            'offers' => $offers,
            'options' => $this->options($services)->mapWithKeys(fn (Service $service) => [$service->id => [
                'name' => $service->name,
                'description' => $service->description,
                'amount' => $service->currentPrice(),
                'unit' => $service->pricing_mode === 'unit' ? $service->unit : null,
                'starting' => $service->pricing_mode === 'starting',
            ]])->all(),
            'maintenance' => collect($this->catalog->maintenance())->map(fn (array $plan) => [
                'name' => $plan['name'], 'amount' => $plan['amount_cents'], 'intro' => $plan['intro'], 'starting' => $plan['starting_from'],
            ])->all(),
            'margin' => self::MARGIN,
        ];
    }

    /**
     * Options a visitor can add, optionally limited to one offer.
     *
     * @param  Collection<int, Service>  $services
     * @return Collection<int, Service>
     */
    private function options(Collection $services, ?string $offer = null): Collection
    {
        $sizeUnits = array_column(self::INCLUDED, 'unit');

        return $services->filter(fn (Service $service) => ! in_array($service->category, self::EXCLUDED_CATEGORIES, true)
            && $service->frequency === 'once'
            && in_array($service->pricing_mode, ['fixed', 'starting', 'unit'], true)
            && $service->currentPrice() > 0
            && ! ($service->pricing_mode === 'unit' && in_array($service->unit, $sizeUnits, true))
            && ($offer === null || $this->compatible($service, $offer)))->values();
    }

    private function compatible(Service $service, string $offer): bool
    {
        return empty($service->compatible_offers) || in_array('offer_'.$offer, $service->compatible_offers, true);
    }
}
