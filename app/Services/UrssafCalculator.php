<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UrssafCalculator
{
    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return ['activity' => '', 'acre' => false, 'acre_scheme' => '25', 'liberatory' => false, 'custom' => false, 'social_rate' => '', 'training_rate' => '', 'tax_rate' => '', 'other_rate' => '0'];
    }

    /** @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function settings(array $options, string $month): array
    {
        app(MonthlyAccounting::class)->monthStart($month);
        $data = array_replace($this->defaults(), array_intersect_key($options, $this->defaults()));
        foreach (['social_rate', 'training_rate', 'tax_rate', 'other_rate'] as $key) {
            if (is_string($data[$key])) {
                $data[$key] = str_replace(',', '.', trim($data[$key]));
            }
        }
        $rules = ['activity' => ['required', Rule::in(array_keys(config('urssaf.profiles')))], 'acre' => 'required|boolean', 'acre_scheme' => ['required', Rule::in(['25', '50'])], 'liberatory' => 'required|boolean', 'custom' => 'required|boolean'];
        foreach (['social_rate', 'training_rate', 'tax_rate', 'other_rate'] as $key) {
            $required = $key === 'other_rate' || ($data['custom'] && ($key !== 'tax_rate' || $data['liberatory']));
            $rules[$key] = [$required ? 'required' : 'nullable', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/', 'numeric', 'between:0,100'];
        }
        Validator::make($data, $rules, ['activity.required' => 'Choisissez votre catégorie d’activité.', '*.required' => 'Renseignez les taux applicables.', '*.regex' => 'Saisissez un taux avec au maximum deux décimales.', '*.between' => 'Le taux doit être compris entre 0 et 100 %.'])->validate();
        if (! $data['custom'] && substr($month, 0, 4) !== (string) config('urssaf.year')) {
            throw ValidationException::withMessages(['custom' => 'Les taux proposés sont ceux de 2026. Activez les taux personnalisés et renseignez ceux de cette période.']);
        }
        if (! $data['custom'] && $data['acre'] && $data['activity'] === 'cipav') {
            throw ValidationException::withMessages(['custom' => 'Pour l’ACRE avec la Cipav, utilisez le taux personnalisé indiqué dans votre espace URSSAF.']);
        }
        if (! $data['custom'] && $data['acre'] && $data['acre_scheme'] === '25' && $month < '2026-07') {
            throw ValidationException::withMessages(['acre_scheme' => 'Le dispositif ACRE à 25 % ne concerne que les créations à partir du 1er juillet 2026.']);
        }

        return $data;
    }

    /** @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function estimate(int $cents, array $options, string $month): array
    {
        $data = $this->settings($options, $month);
        $profile = config('urssaf.profiles.'.$data['activity']);
        $rates = [
            'social' => $data['custom'] ? CommercialCalculator::decimal($data['social_rate']) : $profile[$data['acre'] ? 'acre_'.$data['acre_scheme'] : 'social'],
            'training' => $data['custom'] ? CommercialCalculator::decimal($data['training_rate']) : $profile['training'],
            'tax' => $data['liberatory'] ? ($data['custom'] ? CommercialCalculator::decimal($data['tax_rate']) : $profile['tax']) : 0,
            'other' => CommercialCalculator::decimal($data['other_rate']),
        ];
        if ($cents < 0 || $cents > intdiv(PHP_INT_MAX - 5000, 10000) || array_sum($rates) > 10000) {
            throw ValidationException::withMessages(['rates' => 'Vérifiez le montant et les taux : leur somme ne peut pas dépasser 100 %.']);
        }
        $amounts = array_map(fn (int $rate): int => CommercialCalculator::rounded($cents * $rate, 10000), $rates);
        $total = array_sum($amounts);

        return ['rates' => $rates, 'amounts' => $amounts, 'total' => $total, 'remaining' => $cents - $total, 'base' => $cents];
    }
}
