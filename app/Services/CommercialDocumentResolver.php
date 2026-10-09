<?php

namespace App\Services;

use App\Models\CommercialSetting;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\MaintenanceContract;
use App\Models\Service;

class CommercialDocumentResolver
{
    /** @return array<string, mixed> */
    public function prepare(Document $document): array
    {
        $settings = CommercialSetting::current();
        $seller = $settings;
        $project = $document->project;
        $client = $project->client ?? $document->client;
        $quote = $document->quote;
        $input = $document->contract_data ?? [];
        $date = $document->document_date->format('Y-m-d');
        $templateId = $document->cgv_version_id ?: ($input['terms_template_id'] ?? null);
        $template = $templateId ? DocumentTemplate::whereKey($templateId)->first() : app(TermsVersions::class)->applicable($date);
        $parameters = array_replace($settings, $template->parameters ?? []);
        $scope = $quote ? app(ContractScopeBuilder::class)->build($quote) : null;
        $fields = $provenance = $errors = [];
        $checks = 0;
        $check = function (string $key, bool $valid, string $message) use (&$errors, &$checks): void {
            $checks++;
            if (! $valid) {
                $errors[$key] = $message;
            }
        };
        $labels = [];
        foreach (LegalDocumentRules::sections() as $definitions) {
            $labels += $definitions;
        }
        $labels += ['backups' => 'Sauvegardes', 'security' => 'Sécurité et accès', 'source_code' => 'Livraison du code source', 'graphic_files' => 'Fichiers graphiques', 'modification_rights' => 'Droit de modification', 'technologies' => 'Technologies'];
        foreach ($labels as $key => $label) {
            $fields[$key] = $settings['contract_'.$key] ?? '';
            $provenance[$key] = 'Paramètres Codenyr';
            if (array_key_exists($key, $scope['fields'] ?? [])) {
                $fields[$key] = $scope['fields'][$key];
                $provenance[$key] = 'Devis accepté';
            }
            if (array_key_exists($key, $project->contract_data ?? [])) {
                $fields[$key] = $project->contract_data[$key];
                $provenance[$key] = 'Projet';
            }
            if (array_key_exists($key, $input)) {
                $fields[$key] = $input[$key];
                $provenance[$key] = 'Conditions particulières';
            }
        }
        if (! filled($fields['object'])) {
            $fields['object'] = $project->description ?? '';
            $provenance['object'] = 'Projet';
        }
        if (! filled($fields['show_technologies'])) {
            $fields['show_technologies'] = 'non';
            $provenance['show_technologies'] = 'Valeur par défaut';
        }
        foreach (['domain', 'hosting'] as $kind) {
            if ($fields[$kind.'_mode'] === 'not_applicable') {
                $fields[$kind.'_mode'] = 'non_applicable';
            }
            if (in_array($fields[$kind.'_mode'], ['client', 'codenyr'], true)) {
                foreach (['owner', 'manager', 'payer', 'renewal'] as $part) {
                    if (! filled($fields[$kind.'_'.$part])) {
                        $fields[$kind.'_'.$part] = $fields[$kind.'_mode'];
                        $provenance[$kind.'_'.$part] = 'Gestion sélectionnée';
                    }
                }
            }
        }
        $seller['signatory'] = $seller['signatory'] ?: trim(($seller['signatory_firstname'] ?? '').' '.($seller['signatory_lastname'] ?? ''));
        $seller['address'] = $this->address($seller);
        $clientData = $client?->toArray() ?? [];
        $clientData['address'] = $this->address($clientData);
        $audience = $clientData['audience'] ?? '';
        foreach (['legal_name', 'legal_status', 'address', 'registration', 'email', 'tax_mention', 'signatory', 'signatory_role'] as $key) {
            $check('seller_'.$key, filled($seller[$key]), 'Paramètres Codenyr : '.(LegalDocumentRules::formSources('contract')['seller'][$key] ?? $key).' manquant.');
        }
        $check('seller_siren', filled($seller['siren'] ?? '') || filled($seller['siret'] ?? ''), 'Paramètres Codenyr : SIREN ou SIRET manquant.');
        if ($seller['vat_enabled']) {
            $check('seller_vat', filled($seller['vat_number']), 'Numéro de TVA Codenyr manquant.');
        }
        $check('parties', $client !== null, 'Choisissez un client.');
        $check('audience', in_array($audience, ['professional', 'consumer'], true), 'Précisez la qualité du client dans sa fiche.');
        foreach (['name', 'address', 'country', 'email', 'signatory'] as $key) {
            $check('client_'.$key, filled($clientData[$key] ?? ''), 'Fiche client : '.LegalDocumentRules::formSources('contract')['client'][$key].' manquant.');
        }
        if ($audience === 'professional') {
            foreach (['registration', 'signatory_role'] as $key) {
                $check('client_'.$key, filled($clientData[$key] ?? ''), 'Fiche client : '.LegalDocumentRules::formSources('contract')['client'][$key].' manquant.');
            }
        }
        $check('relations', ! $project || $project->client_id === $document->client_id, 'Le client et le projet ne correspondent pas.');
        $check('quote_relation', ! $quote || ($project && $quote->client_project_id === $project->id && (int) ($quote->snapshot['client']['id'] ?? $project->client_id) === $client?->id), 'Le devis ne correspond pas au projet ou au client.');
        $invoice = $document->invoice;
        $check('invoice_relation', ! $invoice || ($invoice->client_project_id === $project?->id && (! $invoice->quote_id || $invoice->quote_id === $quote?->id)), 'La facture ne correspond pas au projet et au devis.');
        $needsTerms = $document->type === 'terms' || ($settings['require_contract_terms'] && in_array($document->type, ['contract', 'maintenance']));
        if ($needsTerms) {
            $check('terms_template_id', $template?->type === 'terms' && $template->status === 'active', 'Sélectionnez une version active des CGV.');
            $check('terms_effective', $template?->effective_on && $template->effective_on <= $date, 'Les CGV doivent être applicables à la date du document.');
        }
        if ($quote) {
            $total = $quote->totals['initial'];
            $check('amounts', $total['ttc'] === $total['ht'] + $total['vat'] && $total['ttc'] === $quote->totals['deposit'] + $quote->totals['remaining'], 'Montants incohérents dans le devis.');
            foreach ($scope['errors'] ?? [] as $key => $message) {
                $check($key, false, $message);
            }
        }
        $reference = $template ? $template->name.' — version '.($template->version_label ?: $template->revision).' — effet '.$template->effective_on : '';
        $sections = [];
        $preamble = '';
        if ($document->type === 'terms') {
            $map = ['scope' => 'Objet et champ d’application', 'order' => 'Devis et formation du contrat', 'prices' => 'Prix', 'payment' => 'Modalités de paiement', 'late_'.$audience => 'Retards de paiement', 'delays' => 'Délais et exécution', 'client_duties' => 'Obligations du client', 'changes' => 'Modifications et hors périmètre', 'delivery' => 'Validation et livraison', 'rights' => 'Propriété intellectuelle', 'hosting' => 'Hébergement et domaine', 'maintenance' => 'Maintenance', 'liability' => 'Responsabilité', 'security' => 'Sauvegarde et sécurité', 'privacy' => 'Données personnelles', 'termination' => 'Résiliation', 'force_majeure' => 'Force majeure', 'confidentiality' => 'Confidentialité', 'disputes_'.$audience => 'Droit applicable et règlement des différends', 'priority' => 'Ordre de priorité contractuel', 'acceptance' => 'Acceptation'];
            foreach ($map as $key => $title) {
                if ($key === 'hosting' && $fields['domain_mode'] === 'non_applicable' && $fields['hosting_mode'] === 'non_applicable') {
                    continue;
                }
                $body = $parameters['clause_'.$key] ?? '';
                if ($key === 'maintenance' && $fields['maintenance_mode'] === 'aucune') {
                    $body = $parameters['clause_maintenance_none'] ?: 'La maintenance récurrente n’est pas comprise dans les prestations.';
                }
                if (filled($body)) {
                    $sections[$title] = $body;
                }
            }
            foreach (['domain' => 'Nom de domaine', 'hosting' => 'Hébergement'] as $kind => $title) {
                $variant = $parameters['clause_'.$kind.'_'.$fields[$kind.'_mode']] ?? '';
                if (filled($variant)) {
                    $sections[$title] = $variant;
                }
            }
            $preamble = $template->content ?? '';
            $check('clauses', $sections !== [] || filled($preamble), 'Renseignez les clauses dans la version des CGV.');
            if (filled($parameters['external_fees_mention'])) {
                $sections['Services et frais tiers'] = $parameters['external_fees_mention'];
            }
        } elseif ($document->type === 'maintenance') {
            $maintenance = MaintenanceContract::find($document->maintenance_contract_id);
            $check('maintenance_source', $maintenance !== null && $maintenance->client_project_id === $project?->id, 'Sélectionnez la souscription de maintenance de ce projet.');
            if ($maintenance) {
                foreach (['included', 'renewal', 'response_time', 'termination'] as $key) {
                    $check('maintenance_'.$key, filled($maintenance->$key), 'Complétez la souscription : '.(['included' => 'prestations incluses', 'renewal' => 'renouvellement', 'response_time' => 'délai d’intervention', 'termination' => 'résiliation'][$key]).'.');
                }
                $sections = ['Formule et prestations' => $maintenance->name."\n".$maintenance->included, 'Prix et périodicité' => CommercialCalculator::euros($maintenance->amount_cents).' — '.(Service::FREQUENCIES[$maintenance->frequency] ?? $maintenance->frequency), 'Durée et renouvellement' => 'Début : '.$maintenance->starts_on->format('d/m/Y').' — durée : '.$maintenance->duration_months.' mois'."\n".$maintenance->renewal, 'Exclusions' => $maintenance->exclusions, 'Sauvegardes et sécurité' => trim($maintenance->backups."\n".$maintenance->security), 'Interventions' => $maintenance->response_time, 'Résiliation' => $maintenance->termination, 'Documents contractuels' => $reference];
            }
        } else {
            $check('project', $project !== null, 'Choisissez un projet.');
            if ($settings['require_contract_quote']) {
                $check('quote', $quote?->status === 'accepted' && $quote->finalized_at !== null, 'Associez un devis finalisé et accepté.');
            }
            foreach (['object', 'deliverables', 'start_event', 'review_days', 'revision_rounds', 'ip_mode', 'domain_mode', 'hosting_mode', 'maintenance_mode', 'privacy_processing'] as $key) {
                $choices = LegalDocumentRules::choices($key);
                $check($key, filled($fields[$key]) && $fields[$key] !== 'indetermine' && (! $choices || in_array($fields[$key], $choices)), 'Complétez : '.$labels[$key].'.');
            }
            foreach (['review_days', 'revision_rounds'] as $key) {
                $check($key.'_number', ctype_digit((string) $fields[$key]), $labels[$key].' : saisissez un nombre entier positif ou zéro.');
            }
            foreach (LegalDocumentRules::sections()['Propriété intellectuelle'] as $key => $label) {
                if (LegalDocumentRules::active($key, $fields)) {
                    $check($key, filled($fields[$key]), 'Complétez : '.$label.'.');
                }
            }
            $start = $project?->starts_on;
            $due = $project?->due_on;
            $check('starts_on', $start !== null, 'Date de début manquante dans le projet.');
            $check('due_on', $due !== null && (! $start || $due >= $start), 'Date de livraison manquante ou antérieure au début.');
            if ($fields['privacy_processing'] === 'oui') {
                $check('privacy_annex_id', Document::whereKey($fields['privacy_annex_id'] ?: 0)->where('client_project_id', $project?->id)->where('client_id', $client?->id)->where('type', 'legal')->whereNull('archived_at')->whereNotNull('path')->exists(), 'Associez l’annexe relative au traitement des données de ce projet.');
            }
            $price = $quote ? $quote->number."\nHT : ".CommercialCalculator::euros($quote->totals['initial']['ht']).' — TVA : '.CommercialCalculator::euros($quote->totals['initial']['vat']).' — TTC : '.CommercialCalculator::euros($quote->totals['initial']['ttc'])."\nAcompte : ".CommercialCalculator::euros($quote->totals['deposit']).' — Solde : '.CommercialCalculator::euros($quote->totals['remaining'])."\n".$quote->conditions : '';
            $sections = ['Objet du contrat' => $fields['object'], 'Périmètre et livrables' => $fields['deliverables'], 'Éléments exclus' => $fields['exclusions'], 'Planning et obligations' => 'Début : '.$start?->format('d/m/Y').' — Livraison : '.$due?->format('d/m/Y')."\nDurée : ".($quote?->estimated_delay ?: ($start && $due ? $start->diffInDays($due).' jours' : ''))."\n".$fields['start_event'], 'Prix et paiement' => $price, 'Livraison, recette et validation' => $this->lines($fields, LegalDocumentRules::sections()['Livraison et validation']), 'Propriété intellectuelle spécifique' => $this->lines($fields, LegalDocumentRules::sections()['Propriété intellectuelle'])."\n".$this->lines($fields, array_intersect_key($labels, array_flip(['source_code', 'graphic_files', 'modification_rights']))), 'Domaine, hébergement et services tiers' => $this->lines($fields, LegalDocumentRules::sections()['Domaine et hébergement'])."\n".$project?->domain."\n".$project?->host."\n".$fields['third_party_fees'], 'Maintenance' => $fields['maintenance_mode'] === 'aucune' ? 'Aucune maintenance récurrente n’est comprise.' : ($scope['clauses']['Maintenance'] ?? $fields['maintenance_mode']), 'Données, accès et sécurité' => $this->lines($fields, ['privacy_processing' => 'Traitement de données pour le client', 'privacy_annex_id' => 'Annexe associée', 'backups' => 'Sauvegardes', 'security' => 'Sécurité et accès']), 'Conditions particulières' => $fields['additional'], 'Documents contractuels et CGV applicables' => trim($reference."\n".($quote->number ?? '')."\n".($parameters['clause_priority'] ?? ''))];
            if ($fields['show_technologies'] === 'oui' && filled($fields['technologies'])) {
                $sections['Technologies'] = $fields['technologies'];
            }
            foreach ($scope['clauses'] ?? [] as $title => $body) {
                if ($title !== 'Maintenance') {
                    $sections['Option — '.$title] = $body;
                }
            }
        }
        $context = ['company' => $seller, 'client' => $clientData, 'project' => $project?->attributesToArray() ?? [], 'quote' => [...($quote?->attributesToArray() ?? []), 'total' => $quote ? CommercialCalculator::euros($quote->totals['initial']['ttc']) : '', 'deposit' => $quote ? CommercialCalculator::euros($quote->totals['deposit']) : '', 'remaining' => $quote ? CommercialCalculator::euros($quote->totals['remaining']) : ''], 'settings' => [...$parameters, 'validation_days' => $fields['review_days']], 'scope' => $scope['scope'] ?? '', 'fields' => $fields];
        $variables = app(DocumentVariables::class);
        $preamble = $variables->resolve($preamble, $context, $errors);
        $sections = array_filter($sections, fn ($body) => filled($body));
        foreach ($sections as $title => $body) {
            $sections[$title] = $variables->resolve((string) $body, $context, $errors);
        }
        $content = ($preamble !== '' ? $preamble."\n\n" : '').collect($sections)->map(fn ($body, $title) => $title."\n".$body)->values()->map(fn ($body, $i) => 'ARTICLE '.($i + 1).' — '.$body)->implode("\n\n");
        $check('content', ! preg_match('/\[\s*à\s*compléter[^\]]*\]|\b(?:TODO|null|undefined|N\/A)\b|\{\{.*?\}\}|à\s+(?:préciser|renseigner|déterminer)/iu', $content), 'Le document contient encore une valeur manquante ou un marqueur non résolu.');
        $snapshot = ['engine_version' => 2, 'seller' => $seller, 'client' => $clientData, 'project' => $project?->attributesToArray(), 'quote' => $quote?->attributesToArray(), 'invoice' => $invoice?->attributesToArray(), 'terms' => $template?->toArray(), 'audience' => $audience, 'fields' => $fields, 'provenance' => $provenance, 'commercial_scope' => $scope, 'clauses' => $sections, 'sections' => $sections, 'preamble' => $preamble, 'content' => $content, 'settings' => $parameters, 'signatories' => ['seller' => ['name' => $seller['signatory'], 'role' => $seller['signatory_role']], 'client' => ['name' => $clientData['signatory'] ?? '', 'role' => $clientData['signatory_role'] ?? '']], 'maintenance' => $project?->maintenance->toArray() ?? [], 'sources' => ['client_id' => $client?->id, 'project_id' => $project?->id, 'quote_id' => $quote?->id, 'cgv_version_id' => $template?->id]];

        return ['content' => $content, 'snapshot' => $snapshot, 'errors' => $errors, 'warnings' => [], 'fields' => $fields, 'provenance' => $provenance, 'variableFields' => [], 'percent' => $errors ? max(0, (int) floor(100 * ($checks - count($errors)) / max(1, $checks))) : 100];
    }

    /** @param array<string, mixed> $data */
    private function address(array $data): string
    {
        return implode("\n", array_filter([$data['address'] ?? '', $data['address_extra'] ?? '', trim(($data['postal_code'] ?? '').' '.($data['city'] ?? '')), $data['country'] ?? '']));
    }

    /** @param array<string, mixed> $fields
     * @param  array<string, string>  $labels
     */
    private function lines(array $fields, array $labels): string
    {
        $lines = [];
        foreach ($labels as $key => $label) {
            if (LegalDocumentRules::active($key, $fields) && filled($fields[$key] ?? '')) {
                $lines[] = $label.' : '.$fields[$key];
            }
        }

        return implode("\n", $lines);
    }
}
