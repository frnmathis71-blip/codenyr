<?php

namespace App\Livewire\Admin;

use App\Models\Price;
use App\Services\PricingCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class Pricing extends AdminComponent
{
    /** @var array<string, mixed> Values supplied by the form, validated before persistence. */
    public array $prices = [];

    public function mount(PricingCatalog $catalog): void
    {
        foreach ($catalog->all() as $key => $plan) {
            $this->prices[$key] = number_format($plan['amount_cents'] / 100, 2, ',', '');
        }
    }

    public function save(PricingCatalog $catalog): void
    {
        $plans = $catalog->all();
        $rules = [];
        foreach ($plans as $key => $plan) {
            if (isset($this->prices[$key]) && is_string($this->prices[$key])) {
                $this->prices[$key] = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim($this->prices[$key]));
            }
            $rules['prices.'.$key] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'];
        }
        $this->validate($rules, [
            'required' => 'Renseignez un prix.',
            'numeric' => 'Saisissez un montant en euros.',
            'decimal' => 'Utilisez au maximum deux décimales.',
            'min' => 'Le prix ne peut pas être négatif.',
            'max' => 'Le prix doit rester inférieur à 1 000 000 €.',
        ]);

        DB::transaction(function () use ($plans): void {
            foreach (array_keys($plans) as $key) {
                Price::updateOrCreate(['key' => $key], ['amount_cents' => (int) round((float) $this->prices[$key] * 100)]);
            }
        });
        $this->mount($catalog);
        session()->flash('success', 'Tarifs enregistrés. Les nouveaux prix sont maintenant affichés sur le site.');
    }

    public function render(PricingCatalog $catalog): View
    {
        return view('livewire.admin.pricing', ['plans' => $catalog->all()])->layout('components.admin-layout', ['title' => 'Tarifs']);
    }
}
