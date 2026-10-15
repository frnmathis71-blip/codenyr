<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\CommercialSetting;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Quote;
use Carbon\Carbon;

class ProjectLegalDocuments
{
    public function content(ClientProject $project, string $type, ?Quote $quote = null, string $audience = ''): string
    {
        $document = new Document(['client_id' => $project->client_id, 'client_project_id' => $project->id, 'quote_id' => $quote?->id, 'type' => $type, 'document_date' => today(), 'contract_data' => []]);

        return $this->prepare($document)['content'];
    }

    /** @return array<string, mixed> */
    public function prepare(Document $document): array
    {
        if ($document->finalized_at) {
            return ['content' => $document->content, 'snapshot' => $document->snapshot, 'errors' => [], 'warnings' => [], 'percent' => 100, 'fields' => $document->snapshot['fields'] ?? [], 'provenance' => []];
        }
        if ((int) $document->engine_version >= 2) {
            return app(CommercialDocumentResolver::class)->prepare($document);
        }
        $seller = CommercialSetting::current();
        if (! filled($seller['signatory'])) {
            $seller['signatory'] = trim(($seller['signatory_firstname'] ?? '').' '.($seller['signatory_lastname'] ?? ''));
        }
        $project = $document->project()->with(['client', 'maintenance'])->first();
        $client = $project->client ?? $document->client;
        $quote = $document->quote;
        $input = $document->contract_data ?? [];
        $template = DocumentTemplate::whereKey($input['terms_template_id'] ?? null)->first();
        if (array_key_exists('terms_content', $input)) {
            $template ??= new DocumentTemplate(['type' => 'terms']);
            foreach (LegalDocumentRules::formSources($document->type)['terms'] as $key => $label) {
                if (array_key_exists('terms_'.$key, $input)) {
                    $template->$key = trim((string) $input['terms_'.$key]);
                }
            }
        }
        if ($template?->parameters) {
            $seller = array_replace($seller, $template->parameters);
        }
        // Overrides belong to this document only; source records and accepted quotes stay intact.
        foreach (LegalDocumentRules::formSources($document->type) as $group => $definitions) {
            foreach ($definitions as $key => $label) {
                if (! array_key_exists($group.'_'.$key, $input)) {
                    continue;
                }
                $override = trim((string) $input[$group.'_'.$key]);
                if ($group === 'seller') {
                    $seller[$key] = $override;
                } elseif ($group === 'client' && $client) {
                    $client->$key = $override;
                } elseif ($group === 'project' && $project) {
                    if (in_array($key, ['starts_on', 'due_on']) && $override !== '' && ! validator(['date' => $override], ['date' => 'date_format:Y-m-d'])->passes()) {
                        continue;
                    }
                    if ($key === 'technologies') {
                        $project->contract_data = [...($project->contract_data ?? []), 'technologies' => $override];
                    } else {
                        $project->$key = $override ?: null;
                    }
                } elseif ($group === 'quote' && $quote) {
                    $quote->$key = $override;
                }
            }
        }
        foreach (LegalDocumentRules::CLAUSES as $key => $label) {
            if (array_key_exists('clause_'.$key, $input)) {
                $seller['clause_'.$key] = trim((string) $input['clause_'.$key]);
            }
        }
        $fields = $provenance = $errors = $warnings = [];
        $checks = 0;
        $check = function (string $key, bool $valid, string $label) use (&$errors, &$checks): void {
            $checks++;
            if (! $valid) {
                $errors[$key] = $label;
            }
        };
        $supplied = fn ($value) => $value !== null && trim((string) $value) !== '';
        $present = fn ($value) => $supplied($value) && ! in_array($value, ['indetermine', 'À déterminer', 'À préciser'], true);
        foreach (LegalDocumentRules::sections() as $section) {
            foreach ($section as $key => $label) {
                $business = $project->contract_data[$key] ?? null;
                $fields[$key] = $supplied($business) ? $business : ($supplied($input[$key] ?? null) ? ($input[$key] ?? '') : ($seller['contract_'.$key] ?? ''));
                $provenance[$key] = $supplied($business) ? 'Projet' : ($supplied($input[$key] ?? null) ? 'Document' : 'Paramètres commerciaux');
            }
        }
        if (! $present($fields['object'])) {
            $fields['object'] = $project->description ?? '';
            $provenance['object'] = 'Projet';
        }
        if (! $supplied($fields['show_technologies'])) {
            $fields['show_technologies'] = 'non';
            $provenance['show_technologies'] = 'Valeur par défaut';
        }
        $scope = $quote ? app(ContractScopeBuilder::class)->build($quote) : null;
        if ($scope && $scope['frozen']) {
            foreach ($scope['fields'] as $key => $field) {
                if ($key === 'privacy_processing' && $field === 'non' && $fields[$key] === 'oui') {
                    continue;
                }
                $fields[$key] = $field;
                $provenance[$key] = 'Devis accepté';
            }
            foreach ($scope['errors'] as $key => $message) {
                $check($key, false, $message);
            }
        }
        $activeMaintenance = $project?->maintenance->whereNotIn('status', ['terminated', 'cancelled']) ?? collect();
        if ($activeMaintenance->isNotEmpty() && ! $supplied($fields['maintenance_mode'])) {
            $fields['maintenance_mode'] = 'personnalisee';
            $provenance['maintenance_mode'] = 'Souscription de maintenance';
        }
        foreach ($fields as $key => $field) {
            if (array_key_exists($key, $input)) {
                $fields[$key] = trim((string) $input[$key]);
                if ($fields[$key] !== (string) $field) {
                    $provenance[$key] = 'Document';
                }
            }
        }
        $audience = $client?->audience;
        $check('parties', $client !== null, 'Choisir un client.');
        $check('audience', in_array($audience, ['professional', 'consumer'], true), 'Préciser la qualité du client.');
        foreach (['legal_name', 'legal_status', 'address', 'registration', 'email', 'tax_mention', 'signatory', 'signatory_role'] as $key) {
            $check('seller_'.$key, $present($seller[$key] ?? null), 'Prestataire : compléter '.LegalDocumentRules::formSources($document->type)['seller'][$key].'.');
        }
        if ($seller['vat_enabled']) {
            $check('seller_vat', $present($seller['vat_number']), 'Entreprise : numéro de TVA requis.');
        }
        foreach (['name', 'address', 'country', 'email', 'signatory'] as $key) {
            $check('client_'.$key, $present($client?->$key), 'Client : compléter '.LegalDocumentRules::formSources($document->type)['client'][$key].'.');
        }
        if ($audience === 'professional') {
            foreach (['registration', 'signatory_role'] as $key) {
                $check('client_'.$key, $present($client->$key), 'Client professionnel : renseigner '.($key === 'registration' ? 'l’immatriculation' : 'la qualité du signataire').'.');
            }
        }
        $check('relations', ! $project || $project->client_id === $document->client_id, 'Le projet et le client ne correspondent pas.');
        $check('quote_relation', ! $quote || ($project && $quote->client_project_id === $project->id && (int) ($quote->snapshot['client']['id'] ?? $project->client_id) === $project->client_id), 'Le devis appartient à un autre projet ou client.');
        $invoice = $document->invoice;
        $check('invoice_relation', ! $invoice || ($project && $invoice->client_project_id === $project->id && (! $invoice->quote_id || $invoice->quote_id === $quote?->id)), 'La facture ne correspond pas au projet et au devis.');
        $check('terms_template_id', $template?->type === 'terms' && filled($template->name) && filled($template->content) && filled($template->effective_on), 'Compléter le nom, le texte et la date d’effet des CGV ou sélectionner une version.');
        if ($template?->effective_on) {
            $check('terms_effective', $template->effective_on <= $document->document_date->format('Y-m-d'), 'La version des CGV n’est pas encore applicable à cette date.');
        }
        if ($quote) {
            $total = $quote->totals['initial'] ?? [];
            $check('amounts', isset($total['ht'], $total['vat'], $total['ttc'], $quote->totals['deposit'], $quote->totals['remaining']) && $total['ttc'] === $total['ht'] + $total['vat'] && $total['ttc'] === $quote->totals['deposit'] + $quote->totals['remaining'], 'Montants incohérents : corriger le devis source.');
        }
        $clauses = [];
        foreach (LegalDocumentRules::CLAUSES as $key => $label) {
            if ((str_ends_with($key, '_professional') && $audience !== 'professional') || (str_ends_with($key, '_consumer') && $audience !== 'consumer')) {
                continue;
            }
            $clauses[$label] = $seller['clause_'.$key] ?? '';
            $check('clause_'.$key, $present($clauses[$label]), 'Compléter la clause : '.$label.'.');
        }
        $value = fn ($v, $label) => $present($v) ? (string) $v : '[À compléter : '.$label.']';
        $identity = implode("\n", array_filter(array_map(fn ($key) => $seller[$key] ?? '', ['name', 'legal_name', 'legal_status', 'address', 'siren', 'registration', 'email', 'phone', 'vat_number', 'tax_mention'])));
        $parties = $identity."\n\nClient : ".implode(' — ', array_filter(array_map(fn ($key) => $client?->$key, ['name', 'company', 'contact', 'address', 'country', 'registration', 'vat_number', 'email', 'phone'])));
        $termsReference = $template ? $template->name.' v'.$template->revision.' — '.$template->effective_on : '[À compléter : version des CGV]';
        $context = 'Client : '.$client?->name.' ; projet : '.$project?->name.' ; devis : '.$quote?->number."\nCGV : ".$termsReference.' ; date : '.$document->document_date->format('d/m/Y');
        $sections = [];
        if ($document->type === 'terms') {
            // Ordered like a professional CGV: scope, ordering, money, execution, risks, final provisions.
            $versionLabel = $template ? $template->name.' (version '.$template->revision.($template->effective_on ? ', en vigueur depuis le '.Carbon::parse($template->effective_on)->format('d/m/Y') : '').')' : '[À compléter : version des CGV]';
            $sections = [
                'Préambule' => $template->content ?? '',
                'Identification du prestataire' => $identity,
                'Objet et champ d’application' => 'Les présentes conditions générales de vente (CGV) régissent les prestations de conception, de développement, de mise en ligne et, le cas échéant, de maintenance de sites internet et d’applications web réalisées par le prestataire. Le périmètre exact, le prix et les délais de chaque prestation sont fixés par le devis accepté.',
                'Documents contractuels' => 'Le contrat est formé du devis accepté, des présentes CGV et, le cas échéant, du contrat de prestation et de ses annexes. '.$seller['clause_priority'],
                'Devis et formation du contrat' => $seller['clause_order'],
                'Prix' => 'Les prix figurant au devis sont fermes pendant sa durée de validité. Ils sont exprimés en euros. '.$seller['tax_mention'].'. Les frais de services tiers ne sont inclus que lorsqu’ils sont expressément mentionnés au devis.',
                'Modalités de paiement' => $seller['clause_payment'],
                'Retards de paiement' => $seller['clause_late_'.$audience] ?? '',
                'Réserve de propriété' => 'Le transfert au client de la propriété des livrables et des droits d’exploitation n’intervient qu’après le paiement intégral du prix. Jusqu’à ce paiement, le prestataire peut suspendre la mise en ligne ou l’accès aux livrables.',
                'Délais et exécution' => 'Les délais indiqués au devis courent à compter de la réception de l’acompte et des éléments nécessaires (contenus, visuels, accès). Ils sont donnés de bonne foi ; un retard dans la transmission des éléments ou des validations par le client décale d’autant le planning, sans engager la responsabilité du prestataire.',
                'Obligations du client' => 'Le client s’engage à fournir des contenus exacts et licites dont il détient les droits, à répondre aux demandes de validation dans des délais raisonnables et à désigner un interlocuteur. Il garantit le prestataire contre toute réclamation relative aux contenus qu’il transmet.',
                'Modifications et prestations supplémentaires' => 'Toute demande dépassant le périmètre du devis (nouvelle page, nouvelle fonctionnalité, changement de direction graphique) fait l’objet d’un devis complémentaire ou, à défaut, est facturée au temps passé au tarif de '.$seller['extra_hourly_rate'].' € de l’heure, après accord préalable écrit du client.',
                'Validation et livraison' => $seller['clause_delivery'],
                'Propriété intellectuelle' => $seller['clause_rights'],
                'Services et frais tiers' => $seller['external_fees_mention'],
                'Hébergement et nom de domaine' => $seller['clause_hosting'],
                'Maintenance' => $seller['clause_maintenance'],
                'Sous-traitance' => 'Le prestataire peut confier une partie des prestations à des sous-traitants de son choix, en restant seul responsable de leur bonne exécution envers le client.',
                'Responsabilité' => $seller['clause_liability'],
                'Sauvegarde et sécurité' => $seller['clause_security'],
                'Données personnelles' => $seller['clause_privacy'],
                'Confidentialité' => $seller['clause_confidentiality'],
                'Résiliation et annulation' => $seller['clause_termination'],
                'Force majeure' => 'Aucune partie n’est responsable d’un manquement causé par un événement de force majeure au sens de l’article 1218 du Code civil. L’exécution est suspendue pendant sa durée ; si l’empêchement dépasse trente jours, chaque partie peut résilier le contrat par écrit, les prestations déjà réalisées restant dues.',
                'Droit applicable et règlement des différends' => $seller['clause_disputes_'.$audience] ?? '',
                'Dispositions générales' => 'Si une clause des présentes est déclarée nulle, les autres clauses restent applicables. Le fait de ne pas se prévaloir d’un manquement ne vaut pas renonciation à s’en prévaloir ultérieurement. Le prestataire peut faire évoluer ses CGV ; la version applicable est celle en vigueur à la date d’acceptation du devis.',
                'Acceptation et version des CGV' => 'Les présentes conditions, '.$versionLabel.', s’appliquent au devis '.($quote?->number ?? '[À compléter : devis]').' accepté par '.($client?->name ?? '[À compléter : client]').($project ? ' pour le projet « '.$project->name.' »' : '').'. '.$seller['clause_acceptance'],
                'Signataires' => 'Prestataire : '.$seller['signatory'].' — '.$seller['signatory_role']."\nClient : ".$client?->signatory.' — '.$client?->signatory_role,
            ];
        } else {
            $check('project', $project !== null, 'Choisir un projet pour le contrat.');
            $check('quote', $quote !== null && $quote->status === 'accepted' && $quote->finalized_at !== null, 'Associer un devis finalisé et accepté.');
            foreach (['starts_on', 'due_on'] as $key) {
                $check($key, $project?->$key !== null, 'Planning : renseigner '.($key === 'starts_on' ? 'la date de début' : 'la date de livraison').'.');
            }
            $check('estimated_delay', filled($quote?->estimated_delay), 'Renseigner la durée estimée dans le devis.');
            $check('payment', filled($quote?->conditions), 'Renseigner les modalités et échéances dans le devis.');
            foreach (LegalDocumentRules::sections() as $section) {
                foreach ($section as $key => $label) {
                    if (in_array($key, ['additional', 'signature_place', 'annex_state', 'privacy_annex_id'], true)) {
                        continue;
                    }
                    if ($key === 'ip_license' && $fields['ip_mode'] === 'cession') {
                        continue;
                    }
                    if (str_starts_with($key, 'ip_') && $fields['ip_mode'] !== 'cession' && ! in_array($key, ['ip_mode', 'ip_license'])) {
                        continue;
                    }
                    if (str_starts_with($key, 'domain_') && $key !== 'domain_mode' && $fields['domain_mode'] === 'non_applicable') {
                        continue;
                    }
                    if (str_starts_with($key, 'hosting_') && $key !== 'hosting_mode' && $fields['hosting_mode'] === 'non_applicable') {
                        continue;
                    }
                    $choices = LegalDocumentRules::choices($key);
                    $check($key, $present($fields[$key]) && (! $choices || in_array($fields[$key], $choices, true)), 'Compléter : '.$label.'.');
                }
            }
            foreach (['review_days', 'revision_rounds'] as $key) {
                $check($key.'_number', ctype_digit((string) $fields[$key]), 'Saisir un nombre entier positif ou zéro pour '.($key === 'review_days' ? 'le délai de vérification' : 'le nombre de séries de modifications').'.');
            }
            if ($fields['show_technologies'] === 'oui') {
                $check('technologies', filled($project->contract_data['technologies'] ?? null), 'Renseigner les technologies dans le projet.');
            }
            if ($fields['domain_mode'] !== 'non_applicable') {
                $check('domain', filled($project?->domain), 'Renseigner le domaine dans le projet.');
            }
            if ($fields['hosting_mode'] !== 'non_applicable') {
                $check('host', filled($project?->host), 'Renseigner l’hébergeur dans le projet.');
            }
            $maintenance = $project?->maintenance->whereNotIn('status', ['terminated', 'cancelled']) ?? collect();
            if ($fields['maintenance_mode'] === 'aucune') {
                $check('maintenance_conflict', $maintenance->isEmpty(), 'Une maintenance est associée : corriger le choix « aucune ».');
            } else {
                $check('maintenance_source', $maintenance->isNotEmpty() || ! empty($scope['clauses']['Maintenance']), 'Associer la souscription de maintenance au projet.');
                foreach ($maintenance as $m) {
                    $check('maintenance_'.$m->id, $m->document_id !== null && Document::whereKey($m->document_id)->where('client_project_id', $project->id)->whereNotNull('path')->exists(), 'Associer le contrat de maintenance #'.$m->id.'.');
                }
            }
            if ($fields['privacy_processing'] === 'oui') {
                $check('privacy_annex_id', Document::whereKey($fields['privacy_annex_id'] ?: 0)->where('client_project_id', $project?->id)->where('client_id', $client?->id)->where('type', 'legal')->whereNull('archived_at')->whereNotNull('path')->exists(), 'Annexe RGPD / sous-traitance nécessaire : associer un document juridique disponible du projet.');
            }
            $services = collect($quote->items ?? [])->filter(fn ($item) => ! ($item['optional'] ?? false) || ($item['selected'] ?? false));
            foreach (preg_split('/\R/u', (string) $fields['exclusions']) ?: [] as $exclusion) {
                if (mb_strlen(trim($exclusion)) > 3 && $services->contains(fn ($item) => str_contains(mb_strtolower($item['name'].' '.$item['description']), mb_strtolower(trim($exclusion))))) {
                    $check('exclusion_conflict', false, 'Une exclusion figure aussi dans les prestations incluses.');
                }
            }
            $sections = ['Identification des parties' => $parties, 'Objet du contrat' => $fields['object'], 'Périmètre de la prestation' => $services->map(fn ($item) => $item['name'].' — '.$item['description'])->implode("\n"), 'Livrables' => $fields['deliverables'], 'Éléments non compris' => $fields['exclusions']];
            if ($fields['show_technologies'] === 'oui') {
                $sections['Technologies'] = $project->contract_data['technologies'] ?? '';
            }
            $sections['Planning'] = 'Début : '.$project?->starts_on?->format('d/m/Y').' ; livraison : '.$project?->due_on?->format('d/m/Y').' ; durée : '.$quote?->estimated_delay."\n".$fields['start_event'];
            $sections['Prix et échéancier'] = $quote ? $quote->number.' — EUR — HT : '.CommercialCalculator::euros($quote->totals['initial']['ht']).' ; TVA : '.CommercialCalculator::euros($quote->totals['initial']['vat']).' ; TTC : '.CommercialCalculator::euros($quote->totals['initial']['ttc'])."\nAcompte : ".CommercialCalculator::euros($quote->totals['deposit']).' ; restant : '.CommercialCalculator::euros($quote->totals['remaining'])."\n".$quote->conditions : '';
            foreach ($quote?->totals['recurring'] ?? [] as $frequency => $total) {
                if (($total['ttc'] ?? 0) > 0) {
                    $sections['Prix et échéancier'] .= "\nRécurrent ".$frequency.' : '.CommercialCalculator::euros($total['ttc']).' TTC';
                }
            }
            foreach (LegalDocumentRules::sections() as $title => $section) {
                if (in_array($title, ['Projet et périmètre', 'Planning', 'Clauses et annexes', 'Signatures', 'Maintenance'])) {
                    continue;
                }
                $lines = [];
                foreach ($section as $key => $label) {
                    if (LegalDocumentRules::active($key, $fields) && $present($fields[$key])) {
                        $lines[] = $label.' : '.$fields[$key];
                    }
                }
                $sections[$title] = implode("\n", $lines);
            }
            $sections['Recette, corrections et validation'] = 'Une série est un ensemble cohérent de retours transmis en une fois. Les anomalies et corrections du périmètre convenu se distinguent des nouvelles demandes, soumises à accord préalable.';
            $sections['Droits préexistants'] = 'Les éléments du client restent soumis aux droits de leurs titulaires. Les composants tiers conservent leurs licences. Les outils, méthodes et composants génériques préexistants de Codenyr sont distincts des créations spécifiques et des éléments du client.';
            $sections['Domaine et hébergement'] .= "\nDomaine : ".$project?->domain.' ; hébergeur : '.$project?->host;
            $sections['Maintenance'] = $fields['maintenance_mode'] === 'aucune' ? 'Aucune maintenance souscrite. Les corrections et obligations expressément convenues restent applicables.' : $maintenance->map(fn ($m) => $m->name.' — '.CommercialCalculator::euros($m->amount_cents).' / '.$m->frequency.' ; début '.$m->starts_on?->format('d/m/Y').' ; contrat #'.$m->document_id)->implode("\n");
            if ($scope && $scope['frozen']) {
                $sections['Périmètre de la prestation'] = $scope['scope'];
                foreach ($scope['clauses'] as $title => $body) {
                    $sections['Option — '.$title] = $body;
                }
                if (! empty($scope['clauses']['Maintenance'])) {
                    $sections['Maintenance'] = $scope['clauses']['Maintenance'];
                }
            }
            $sections['Clauses applicables'] = collect($clauses)->map(fn ($text, $title) => $title."\n".$value($text, $title))->implode("\n\n");
            $sections['CGV applicables'] = $context."\n".$template?->content;
            $sections['Documents contractuels'] = $quote ? 'Devis '.$quote->number.' — statut : '.$quote->status : '';
            if ($fields['privacy_processing'] === 'oui' && ! isset($errors['privacy_annex_id'])) {
                $sections['Documents contractuels'] .= "\nAnnexe RGPD associée #".$fields['privacy_annex_id'];
            }
            if ($present($fields['additional'])) {
                $sections['Précisions complémentaires'] = $fields['additional'];
            }
            $sections['Signatures'] = 'Codenyr : '.$seller['signatory'].' — '.$seller['signatory_role']."\nClient : ".$client?->signatory.' — '.$client?->signatory_role."\nLieu : ".$fields['signature_place']."\n".$seller['clause_acceptance']."\nDate et signature Codenyr :\n\nDate et signature du client :";
        }
        if ($document->type === 'terms' && ! empty($scope['clauses'])) {
            $sections['Conditions des prestations retenues'] = collect($scope['clauses'])->map(fn ($body, $title) => $title."\n".$body)->implode("\n\n");
        }
        if ($document->type === 'terms' && $present($fields['additional'])) {
            $sections['Précisions complémentaires'] = $fields['additional'];
        }
        $variables = ['{{client}}' => $client->name ?? '', '{{project}}' => $project->name ?? '', '{{seller}}' => $seller['legal_name'] ?: $seller['name']];
        preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/', implode("\n", $sections), $matches, PREG_SET_ORDER);
        $variableFields = [];
        foreach ($matches as $match) {
            $canonical = '{{'.$match[1].'}}';
            if (array_key_exists($canonical, $variables)) {
                $variables[$match[0]] = $variables[$canonical];

                continue;
            }
            $key = 'variable_'.$match[1];
            $variableFields[$key] = $match[1];
            $check($key, $present($input[$key] ?? null), 'Compléter la variable : '.$match[1].'.');
            $variables[$match[0]] = $input[$key] ?? $match[0];
        }
        $sections = array_map(fn ($text) => strtr((string) $text, $variables), $sections);
        $content = collect($sections)->map(fn ($body, $title) => $title."\n".$value($body, $title))->values()->map(fn ($body, $index) => 'ARTICLE '.($index + 1).' — '.$body)->implode("\n\n");
        $check('content', ! preg_match('/\[\s*à\s*compléter[^\]]*\]|\bTODO\b|\{\{.*?\}\}|\{%.*?%\}|à\s+(?:préciser|renseigner|déterminer)/iu', $content), 'Le contenu produit contient des marqueurs non résolus.');
        $warnings[] = '100 % signifie que les contrôles configurés sont satisfaits ; ce n’est pas une certification juridique.';
        $snapshot = ['commercial_scope' => $scope, 'seller' => $seller, 'client' => $client?->toArray(), 'project' => $project?->attributesToArray(), 'quote' => $quote?->attributesToArray(), 'invoice' => $invoice?->attributesToArray(), 'fields' => $fields, 'clauses' => $clauses, 'terms' => $template?->toArray(), 'audience' => $audience, 'content' => $content, 'engine_version' => 1, 'maintenance' => $project?->maintenance->toArray() ?? [], 'referenced_documents' => $project ? $project->documents()->where('id', '!=', $document->id ?? 0)->whereNull('archived_at')->whereNotNull('path')->whereIn('type', ['terms', 'contract', 'maintenance', 'legal'])->get(['id', 'name', 'type', 'original_id', 'pdf_sha256'])->toArray() : [], 'sources' => ['client_id' => $client?->id, 'project_id' => $project?->id, 'quote_id' => $quote?->id, 'invoice_id' => $invoice?->id]];

        return compact('content', 'snapshot', 'errors', 'warnings', 'fields', 'provenance', 'variableFields') + ['percent' => $checks ? (int) floor(100 * ($checks - count($errors)) / $checks) : 0];
    }
}
