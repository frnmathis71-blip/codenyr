<?php

namespace App\Services;

use App\Models\Quote;

/** Builds commitments exclusively from the frozen, selected quote lines. */
class ContractScopeBuilder
{
    /**
     * @return array{
     *     offer: string|null, options: list<string>, custom: list<string>,
     *     included: array<array-key, string>, excluded: array<array-key, string>,
     *     effects: array<string, mixed>, clauses: array<string, string>,
     *     fields: array<string, mixed>, errors: array<string, string>,
     *     frozen: bool, scope: string
     * }
     */
    public function build(Quote $quote): array
    {
        $snapshot = $quote->snapshot['offer_snapshot'] ?? null;
        $items = $snapshot['items'] ?? $quote->items;
        $included = [];
        $excluded = [];
        $effects = [];
        $clauses = [];
        $fields = [];
        $options = [];
        $custom = [];
        $offer = null;
        $errors = [];
        foreach ($items as $item) {
            if (($item['optional'] ?? false) && ! ($item['selected'] ?? false)) {
                continue;
            }
            if (($item['contract_included'] ?? true) === false) {
                continue;
            }
            $rules = $item['contract_rules'] ?? [];
            $key = $item['contract_key'] ?? 'custom_'.count($included);
            $text = $item['name'].(filled($item['description'] ?? '') ? ' — '.$item['description'] : '');
            $included[$key] = $text;
            if (str_starts_with($key, 'offer_')) {
                if ($offer !== null) {
                    $errors['offer_conflict'] = 'Le devis doit contenir une seule offre principale.';
                }
                $offer = $item['name'];
                $fields['object'] = filled($rules['object'] ?? '') ? $rules['object'] : 'Réalisation de la prestation '.$item['name'].' selon le devis accepté.';
            } elseif (empty($item['service_id'])) {
                $custom[] = $item['name'];
            } else {
                $options[] = $item['name'];
            }
            // Empty arrays are intentional removals, never replaced from today's catalog.
            foreach ($rules['deliverables'] ?? [] as $feature => $label) {
                $included[$feature] = $label;
            }
            if (filled($rules['page_count'] ?? null)) {
                $included['page_count'] = 'Jusqu’à '.$rules['page_count'].' pages prévues au devis.';
            }
            if (filled($rules['annex'] ?? '')) {
                $clauses['Annexe fonctionnelle / Cahier des charges'] = $rules['annex'];
            }
            $excluded = array_replace($excluded, $rules['exclusions'] ?? []);
            foreach ($rules['effects'] ?? [] as $effect => $value) {
                if (isset($effects[$effect]) && $effects[$effect] !== $value) {
                    $errors['effect_'.$effect] = 'Options incompatibles pour '.$effect.' : corriger le devis.';
                }
                $effects[$effect] = $value;
            }
            foreach ($rules['clauses'] ?? [] as $title => $body) {
                $clauseKey = $title === 'Maintenance' ? $title : $item['name'].' — '.$title;
                $clauses[$clauseKey] = $body;
            }
            $fields = array_replace($fields, $rules['fields'] ?? []);
        }
        if (isset($effects['maintenance_mode']) && $effects['maintenance_mode'] !== 'aucune' && empty($clauses['Maintenance'])) {
            $errors['maintenance_terms'] = 'Renseigner les conditions de la maintenance achetée dans le devis.';
        }
        foreach ($included as $key => $label) {
            unset($excluded[$key]);
            foreach ($excluded as $excludedKey => $excludedLabel) {
                if (mb_strtolower(trim($excludedLabel)) === mb_strtolower(trim($label))) {
                    unset($excluded[$excludedKey]);
                }
            }
        }
        if ($snapshot !== null) {
            $fields['deliverables'] = implode("\n", $included);
            $fields['exclusions'] = $excluded ? implode("\n", $excluded) : 'Aucune exclusion supplémentaire ; seules les prestations du devis sont comprises.';
            $fields['maintenance_mode'] = $effects['maintenance_mode'] ?? 'aucune';
            $fields['privacy_processing'] = ! empty($effects['personal_data']) ? 'oui' : 'non';
            foreach ($effects as $key => $value) {
                if (str_starts_with($key, 'domain_') || str_starts_with($key, 'hosting_')) {
                    $fields[$key] = $value;
                }
            }
        }

        return compact('offer', 'options', 'custom', 'included', 'excluded', 'effects', 'clauses', 'fields', 'errors') + ['frozen' => $snapshot !== null, 'scope' => implode("\n", $included)];
    }
}
