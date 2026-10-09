<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContractCatalogRules
{
    /** @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    public static function editor(array $rules): array
    {
        return [
            'object' => $rules['object'] ?? '',
            'deliverables' => implode("\n", $rules['deliverables'] ?? []),
            'exclusions' => implode("\n", $rules['exclusions'] ?? []),
            'clause' => implode("\n", $rules['clauses'] ?? []),
            'personal_data' => (bool) ($rules['effects']['personal_data'] ?? false),
            'maintenance_mode' => $rules['effects']['maintenance_mode'] ?? '',
            'domain_mode' => $rules['effects']['domain_mode'] ?? '',
            'hosting_mode' => $rules['effects']['hosting_mode'] ?? '',
            'revision_rounds' => $rules['fields']['revision_rounds'] ?? '',
            'delivery_days' => $rules['delivery_days'] ?? '',
            'page_count' => $rules['page_count'] ?? '',
            'annex' => $rules['annex'] ?? '',
            'legal' => $rules['fields'] ?? [],
            'responsibilities' => array_intersect_key($rules['effects'] ?? [], array_flip(self::responsibilityFields())),
        ];
    }

    /** @return list<string> */
    public static function responsibilityFields(): array
    {
        return ['domain_owner', 'domain_payer', 'domain_renewal', 'hosting_owner', 'hosting_payer', 'hosting_renewal'];
    }

    /** @param array<string, mixed> $editor
     * @param  array<string, mixed>  $original
     * @return array<string, mixed>
     */
    public static function fromEditor(array $editor, array $original = []): array
    {
        Validator::make($editor, [
            'object' => 'nullable|string|max:5000', 'deliverables' => 'nullable|string|max:20000',
            'exclusions' => 'nullable|string|max:20000', 'clause' => 'nullable|string|max:20000',
            'personal_data' => 'boolean', 'maintenance_mode' => 'nullable|in:aucune,essentiel,pro,business,personnalisee',
            'domain_mode' => 'nullable|in:client,codenyr,non_applicable', 'hosting_mode' => 'nullable|in:client,codenyr,non_applicable',
            'revision_rounds' => 'nullable|integer|min:0', 'delivery_days' => 'nullable|integer|min:0',
            'page_count' => 'nullable|integer|min:1|max:10000', 'annex' => 'nullable|string|max:2000',
            'legal' => 'sometimes|array', 'legal.*' => 'nullable|string|max:20000',
            'responsibilities' => 'sometimes|array', 'responsibilities.*' => 'nullable|in:client,codenyr,non_applicable',
        ])->validate();
        $rules = $original;
        foreach (['deliverables', 'exclusions'] as $kind) {
            $rules[$kind] = [];
            foreach ((preg_split('/\R/u', $editor[$kind] ?? '') ?: []) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $key = array_search($line, $original[$kind] ?? [], true);
                    $rules[$kind][$key !== false ? $key : Str::slug($line)] = $line;
                }
            }
        }
        $rules['object'] = $editor['object'] ?? '';
        $rules['effects'] = array_filter($rules['effects'] ?? [], fn ($key) => ! in_array($key, ['personal_data', 'maintenance_mode']) && ! str_starts_with($key, 'domain_') && ! str_starts_with($key, 'hosting_'), ARRAY_FILTER_USE_KEY);
        if ($editor['personal_data'] ?? false) {
            $rules['effects']['personal_data'] = true;
        }
        if (filled($editor['maintenance_mode'] ?? '')) {
            $rules['effects']['maintenance_mode'] = $editor['maintenance_mode'];
        }
        foreach (['domain', 'hosting'] as $kind) {
            if (filled($editor[$kind.'_mode'] ?? '')) {
                foreach (['mode', 'owner', 'manager', 'payer', 'renewal'] as $field) {
                    $rules['effects'][$kind.'_'.$field] = $editor[$kind.'_mode'];
                }
            }
        }
        foreach (self::responsibilityFields() as $field) {
            if (filled($editor['responsibilities'][$field] ?? '')) {
                $rules['effects'][$field] = $editor['responsibilities'][$field];
            }
        }
        $legalKeys = array_keys(LegalDocumentRules::sections()['Propriété intellectuelle']);
        foreach ($legalKeys as $key) {
            if (filled($editor['legal'][$key] ?? '')) {
                $rules['fields'][$key] = $editor['legal'][$key];
            } else {
                unset($rules['fields'][$key]);
            }
        }
        $rules['clauses'] = filled($editor['clause'] ?? '') ? [(filled($editor['maintenance_mode'] ?? '') ? 'Maintenance' : 'Conditions particulières') => $editor['clause']] : [];
        if (filled($editor['revision_rounds'] ?? '')) {
            $rules['fields']['revision_rounds'] = (string) $editor['revision_rounds'];
        } else {
            unset($rules['fields']['revision_rounds']);
        }
        $rules['delivery_days'] = filled($editor['delivery_days'] ?? '') ? (int) $editor['delivery_days'] : null;
        $rules['page_count'] = filled($editor['page_count'] ?? '') ? (int) $editor['page_count'] : null;
        $rules['annex'] = $editor['annex'] ?? '';

        return self::validate($rules);
    }

    /** @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    public static function validate(array $rules): array
    {
        Validator::make($rules, [
            'object' => 'sometimes|nullable|string|max:5000',
            'deliverables' => 'sometimes|array', 'deliverables.*' => 'string|max:5000',
            'exclusions' => 'sometimes|array', 'exclusions.*' => 'string|max:5000',
            'clauses' => 'sometimes|array', 'clauses.*' => 'string|max:20000',
            'fields' => 'sometimes|array', 'fields.*' => 'string|max:20000',
            'effects' => 'sometimes|array',
            'effects.personal_data' => 'sometimes|boolean',
            'effects.maintenance_mode' => 'sometimes|in:aucune,essentiel,pro,business,personnalisee',
            'delivery_days' => 'sometimes|nullable|integer|min:0',
            'page_count' => 'sometimes|nullable|integer|min:1|max:10000',
            'annex' => 'sometimes|nullable|string|max:2000',
        ])->validate();

        return $rules;
    }

    /** @return array<string, mixed> */
    public static function forService(Service $service): array
    {
        return $service->contract_rules ?? [];
    }

    /** @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public static function freeze(array $items): array
    {
        // Normalization captures catalog data when adding/saving the draft.
        // Legacy lines deliberately get no inferred catalog promises.
        return ['version' => 1, 'items' => array_values($items)];
    }
}
