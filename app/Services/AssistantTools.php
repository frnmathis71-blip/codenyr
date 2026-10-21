<?php

namespace App\Services;

use App\Models\AdminTask;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\CommercialSetting;
use App\Models\Document;
use App\Models\Lead;
use App\Models\ProjectEvent;
use App\Models\ProjectNote;
use App\Models\Quote;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What the admin assistant may do: read clients and prospects, then prepare drafts
 * (client, project, quote, tasks, notes). Nothing here sends, finalizes or deletes:
 * every document stays a draft the admin reviews in the usual editors.
 *
 * @phpstan-import-type ToolResult from AssistantToolset
 * @phpstan-import-type ToolSpec from AssistantToolset
 */
class AssistantTools implements AssistantToolset
{
    public function prompt(): string
    {
        $legal = config('codenyr.legal');

        return <<<PROMPT
        Tu es l’assistant administratif de Codenyr, micro-entreprise française de création de sites web. Tu travailles pour son fondateur, seul utilisateur de cette interface. Il te décrit un client et son besoin ; tu prépares toute la paperasse dans l’application, puis tu lui présentes ce que tu as fait pour qu’il relise et corrige.

        Ce que tu fais, dans cet ordre, quand un nouveau client est décrit :
        1. Cherche s’il existe déjà (find_clients, sur le nom puis l’e-mail) : client ou prospect venu du formulaire du site. Ne crée jamais de doublon ; s’il existe un prospect, reprends ses informations et relie le projet au prospect (lead_id).
        2. Crée ou complète la fiche client, puis crée le projet avec une description claire du besoin.
        3. Prépare le devis en brouillon à partir du catalogue ci-dessous : une offre principale, puis les options nécessaires et, si le client la demande, la maintenance. N’invente pas de prix : utilise ceux du catalogue. Une ligne libre n’est justifiée que si rien dans le catalogue ne correspond, et son prix doit venir de l’utilisateur ; s’il manque, demande-le.
        4. Ajoute les tâches de suivi utiles dans « À faire » (ex. « Envoyer le devis DEV-… à Boulangerie Martin », informations à récupérer) et une note au dossier si l’utilisateur a donné des détails qui ne rentrent pas ailleurs.
        5. Termine par un récapitulatif court : ce qui a été créé (numéro et montant du devis), les hypothèses que tu as faites, et ce qu’il reste à vérifier ou à fournir.

        Documents de démarrage (CGV et contrat de prestation), une fois le devis accepté :
        1. prepare_legal_documents prépare les brouillons à partir des modèles de l’application et renvoie les contrôles bloquants, la fiche des références et le texte.
        2. Pour chaque erreur, complète la source si l’information est connue (fiche client : update_client ; projet : update_project_details ; cherche dans le dossier et ses notes), puis relance prepare_legal_documents. Ce qui reste inconnu, liste-le comme « à fournir » ; n’invente jamais une adresse, un SIRET ou un signataire.
        3. Relis les brouillons et vérifie que tout est bien référencé et cohérent : mêmes parties partout (raison sociale, adresse, immatriculation, signataire et sa qualité), numéro et montants du devis accepté, acompte, délai, dates de début et de livraison, nom de domaine et hébergeur, version des CGV, maintenance. Vérifie la conformité selon la qualité du client : particulier → droit de rétractation de 14 jours, renonciation si la prestation commence avant, médiateur de la consommation ; professionnel → pénalités de retard et indemnité forfaitaire de 40 € ; mention de TVA conforme au régime. Repère les marqueurs « [À compléter] » et les contradictions.
        4. Tu ne réécris jamais le texte juridique ni les modèles : signale ce qui doit changer. Enregistre ta relecture avec record_legal_review (verdict et points à vérifier).
        5. Rien n’est officiel tant que l’utilisateur n’a pas relu les PDF et cliqué sur « Valider et créer la version officielle » dans le Pilote du dossier : dis-le dans ton compte rendu. Si les sources changent après coup, il faudra préparer de nouveaux brouillons.

        Règles :
        - Une information obligatoire manque (e-mail du client, professionnel ou particulier) : pose la question avant de créer la fiche, en regroupant toutes tes questions en un seul message. Pour les informations facultatives (adresse, SIRET…), avance et liste-les à la fin comme « à compléter ».
        - Tu ne peux rien envoyer, finaliser, signer ni supprimer : tout reste en brouillon. Si on te le demande, explique que l’envoi se fait depuis l’éditeur du devis, et que la version officielle des CGV et du contrat (et la facture d’acompte) se crée depuis le Pilote du dossier, après relecture des brouillons.
        - Les montants sont calculés par l’application : reprends ceux renvoyés par les outils, ne recalcule rien toi-même.
        - Pour modifier un devis existant (ex. « baisse à 1 300 € »), utilise update_quote en renvoyant toutes ses lignes. Pour atteindre un total précis, préfère ajuster le prix d’une ligne ou une remise, et dis lequel tu as choisi.
        - Tâches : rattache chaque tâche à son projet (project_id) et n’en crée pas une qui existe déjà (open_tasks). Si ce que tu viens de faire règle une tâche ouverte du projet (ex. « préciser le besoin » une fois le besoin connu et le devis prêt), coche-la (complete_task).
        - Quand la demande vient du chef de projet, ton compte rendu liste : ce que tu as créé ou modifié (numéros, montants), les tâches ouvertes ou cochées, les informations ajoutées au dossier, et les questions en suspens. Enregistre dans une note au dossier (add_project_note) tout ce que tu as appris ou décidé et qui servira plus tard.
        - Le suivi d’un projet en cours (avancement, statut, retards) relève de l’assistant projets, dans l’onglet voisin.
        - Si un outil renvoie une erreur, corrige la demande et réessaie ; si c’est impossible, explique pourquoi en une phrase.
        - Réponds en français, de façon brève et concrète, sans jargon technique. Pas de titres ni de tableaux : quelques phrases ou une courte liste.
        - Cite les devis et factures par leur numéro exact (DEV-…, FAC-…) et les projets par leur nom exact : l’interface en fait des liens. N’écris pas d’adresse web toi-même.

        Entreprise : Codenyr, {$legal['status']} — {$legal['vat']}.

        {$this->catalog()}
        PROMPT;
    }

    /** @return list<ToolSpec> */
    public function definitions(): array
    {
        $line = ['type' => 'object', 'properties' => [
            'service_id' => ['type' => 'integer', 'description' => 'Prestation du catalogue (son prix, sa description et ses règles de contrat sont repris). Omettre pour une ligne libre.'],
            'name' => ['type' => 'string', 'description' => 'Intitulé. Obligatoire pour une ligne libre ; sinon remplace celui du catalogue.'],
            'description' => ['type' => 'string'],
            'quantity' => ['type' => 'number', 'description' => 'Par défaut 1. Pour une option à l’unité (pages, sections, modules, heures), seulement les unités supplémentaires.'],
            'unit_price' => ['type' => 'number', 'description' => 'Prix unitaire HT en euros. Obligatoire pour une ligne libre ou une prestation « sur devis » ; sinon le prix du catalogue s’applique.'],
            'frequency' => ['type' => 'string', 'enum' => ['once', 'monthly', 'quarterly', 'yearly'], 'description' => 'Ligne libre uniquement (par défaut once).'],
            'optional' => ['type' => 'boolean', 'description' => 'Option proposée au client mais non retenue dans le total.'],
        ], 'required' => []];
        $quote = [
            'items' => ['type' => 'array', 'items' => $line, 'description' => 'Toutes les lignes du devis, dans l’ordre.'],
            'discount_percent' => ['type' => 'number', 'description' => 'Remise globale en %, par défaut 0.'],
            'deposit_percent' => ['type' => 'number', 'description' => 'Acompte en % du total initial ; par défaut celui des paramètres commerciaux. 0 pour aucun acompte.'],
            'estimated_delay' => ['type' => 'string', 'description' => 'Délai de réalisation, ex. « 3 semaines ».'],
        ];
        $clientFields = [
            'audience' => ['type' => 'string', 'enum' => ['professional', 'consumer'], 'description' => 'Professionnel ou particulier : détermine les mentions légales des CGV et du contrat.'],
            'name' => ['type' => 'string', 'description' => 'Nom affiché : raison sociale pour un professionnel, prénom et nom pour un particulier.'],
            'company' => ['type' => 'string'], 'contact' => ['type' => 'string', 'description' => 'Personne à contacter.'],
            'firstname' => ['type' => 'string'], 'lastname' => ['type' => 'string'],
            'email' => ['type' => 'string'], 'phone' => ['type' => 'string'],
            'address' => ['type' => 'string', 'description' => 'Numéro et rue.'], 'postal_code' => ['type' => 'string'], 'city' => ['type' => 'string'], 'country' => ['type' => 'string'],
            'siret' => ['type' => 'string'], 'vat_number' => ['type' => 'string'],
            'signatory' => ['type' => 'string', 'description' => 'Qui signe les documents.'], 'signatory_role' => ['type' => 'string'],
        ];

        return [
            $this->tool('find_clients', 'Cherche des clients et des prospects (demandes reçues par le site) par nom, entreprise ou e-mail. Renvoie aussi leurs projets et devis. À appeler avant toute création pour éviter les doublons.', ['query' => ['type' => 'string']], ['query']),
            $this->tool('create_client', 'Crée la fiche d’un nouveau client.', $clientFields, ['audience', 'name', 'email']),
            $this->tool('update_client', 'Complète ou corrige la fiche d’un client existant. Seuls les champs fournis changent.', ['client_id' => ['type' => 'integer']] + $clientFields, ['client_id']),
            $this->tool('create_project', 'Crée le projet (dossier) d’un client.', [
                'client_id' => ['type' => 'integer'],
                'lead_id' => ['type' => 'integer', 'description' => 'Prospect d’origine, s’il y en a un.'],
                'name' => ['type' => 'string', 'description' => 'Ex. « Site vitrine Boulangerie Martin ».'],
                'type' => ['type' => 'string', 'enum' => ClientProject::TYPES],
                'description' => ['type' => 'string', 'description' => 'Besoin du client, en quelques phrases claires.'],
                'due_on' => ['type' => 'string', 'description' => 'Livraison souhaitée, AAAA-MM-JJ.'],
                'website_url' => ['type' => 'string'], 'domain' => ['type' => 'string'],
            ], ['client_id', 'name', 'type']),
            $this->tool('create_quote', 'Prépare un devis en brouillon pour un projet. Les montants, la TVA et le numéro sont calculés par l’application. Le devis n’est pas envoyé.', ['project_id' => ['type' => 'integer']] + $quote, ['project_id', 'items']),
            $this->tool('update_quote', 'Remplace le contenu d’un devis encore en brouillon (toutes les lignes sont à fournir). Impossible sur un devis envoyé.', ['quote_id' => ['type' => 'integer']] + $quote, ['quote_id', 'items']),
            $this->tool('add_task', 'Ajoute une tâche dans l’onglet « À faire » de l’admin (relance, information à récupérer…). Avant d’en créer une, vérifie qu’une tâche équivalente n’est pas déjà ouverte sur le projet.', [
                'title' => ['type' => 'string'], 'details' => ['type' => 'string'],
                'category' => ['type' => 'string', 'enum' => AdminTask::CATEGORIES],
                'project_id' => ['type' => 'integer', 'description' => 'Projet concerné : la tâche apparaît dans son dossier. À renseigner dès qu’elle porte sur un projet.'],
            ], ['title', 'category']),
            $this->tool('complete_task', 'Coche une tâche de « À faire » devenue sans objet ou réglée (par l’utilisateur ou par ce que tu viens de faire).', [
                'task_id' => ['type' => 'integer'],
            ], ['task_id']),
            $this->tool('update_project_details', 'Complète les informations d’un projet utilisées par le contrat : description du besoin, dates, nom de domaine, hébergeur, adresse du site. Le statut relève de l’assistant projets.', [
                'project_id' => ['type' => 'integer'],
                'description' => ['type' => 'string'], 'starts_on' => ['type' => 'string', 'description' => 'AAAA-MM-JJ'], 'due_on' => ['type' => 'string', 'description' => 'AAAA-MM-JJ'],
                'domain' => ['type' => 'string'], 'host' => ['type' => 'string', 'description' => 'Hébergeur.'], 'website_url' => ['type' => 'string'],
            ], ['project_id']),
            $this->tool('prepare_legal_documents', 'Prépare (ou met à jour) les brouillons des CGV et du contrat de prestation d’un projet dont le devis est accepté, avec les contrôles de conformité de l’application. Rien n’est officiel : l’utilisateur relit puis valide depuis le Pilote du dossier. Renvoie les erreurs bloquantes, une fiche des références reprises et le texte des brouillons.', [
                'project_id' => ['type' => 'integer'],
            ], ['project_id']),
            $this->tool('record_legal_review', 'Enregistre ta relecture des brouillons (affichée à l’utilisateur dans le Pilote du dossier, à côté des PDF à relire).', [
                'project_id' => ['type' => 'integer'],
                'summary' => ['type' => 'string', 'description' => 'Ce qui a été vérifié et le verdict, en deux ou trois phrases.'],
                'points' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Points à vérifier ou à corriger par l’utilisateur avant de valider. Vide si rien à signaler.'],
            ], ['project_id', 'summary']),
            $this->tool('add_project_note', 'Ajoute une note interne au dossier d’un projet (échanges, décisions, informations utiles).', [
                'project_id' => ['type' => 'integer'], 'content' => ['type' => 'string'],
            ], ['project_id', 'content']),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return ToolSpec
     */
    private function tool(string $name, string $description, array $properties, array $required): array
    {
        return ['name' => $name, 'description' => $description, 'properties' => $properties, 'required' => $required];
    }

    /** Catalog and commercial settings, given to the model once in the system prompt. */
    public function catalog(): string
    {
        $settings = CommercialSetting::current();
        $lines = Service::where('active', true)->whereNull('archived_at')->orderBy('category')->orderBy('position')->get()
            ->map(fn (Service $service): string => sprintf('- #%d [%s] %s — %s / %s, %s%s. %s', $service->id, $service->category, $service->name, $service->priceLabel(), $service->unit, Service::FREQUENCIES[$service->frequency] ?? $service->frequency, $service->compatible_offers ? ', compatible avec : '.implode(', ', $service->compatible_offers) : '', Str::limit((string) $service->description, 300).($service->ai_notes ? ' Consignes : '.Str::limit($service->ai_notes, 400) : '')));

        return 'Paramètres : TVA '.($settings['vat_enabled'] ? 'applicable à '.$settings['vat_rate'].' %' : 'non applicable (franchise en base)')
            .", acompte par défaut {$settings['deposit_percent']} %, validité des devis {$settings['validity_days']} jours, paiement à {$settings['payment_days']} jours.\n"
            ."Catalogue (identifiant, catégorie, nom, prix HT). Les offres « offer_* » sont exclusives : une seule par devis.\n".$lines->implode("\n")
            ."\nClés des offres : ".Service::where('price_key', 'like', 'offer_%')->pluck('price_key', 'id')->map(fn ($key, $id) => "#{$id} = {$key}")->implode(', ');
    }

    public function run(string $name, array $input): array
    {
        try {
            return match ($name) {
                'find_clients' => $this->ok($this->findClients((string) ($input['query'] ?? ''))),
                'create_client' => $this->createClient($input),
                'update_client' => $this->updateClient($input),
                'create_project' => $this->createProject($input),
                'create_quote' => $this->saveQuote($input, null),
                'update_quote' => $this->saveQuote($input, (int) ($input['quote_id'] ?? 0)),
                'add_task' => $this->addTask($input),
                'complete_task' => $this->completeTask($input),
                'update_project_details' => app(ProjectTools::class)->run('update_project', array_intersect_key($input, array_flip(['project_id', 'description', 'starts_on', 'due_on', 'domain', 'host', 'website_url']))),
                'prepare_legal_documents' => $this->prepareLegalDocuments($input),
                'record_legal_review' => $this->recordLegalReview($input),
                'add_project_note' => $this->addNote($input),
                default => $this->error('Outil inconnu : '.$name),
            };
        } catch (ValidationException $e) {
            return $this->error('Refusé par l’application : '.collect($e->errors())->flatten()->implode(' '));
        }
    }

    /** @return array<string, mixed> */
    private function findClients(string $query): array
    {
        $term = '%'.mb_substr(trim($query), 0, 100).'%';
        $clients = Client::where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('company', 'like', $term)->orWhere('contact', 'like', $term))
            ->with(['projects' => fn ($q) => $q->whereNull('archived_at')->with(['quotes', 'tasks' => fn ($q) => $q->whereNull('done_at')])])->limit(10)->get();
        $leads = Lead::whereNull('archived_at')->where(fn ($q) => $q->where('lastname', 'like', $term)->orWhere('firstname', 'like', $term)->orWhere('company', 'like', $term)->orWhere('email', 'like', $term))
            ->latest()->limit(5)->get();

        return [
            'clients' => $clients->map(fn (Client $client): array => [
                ...$client->only(['id', 'name', 'audience', 'company', 'contact', 'email', 'phone', 'address', 'postal_code', 'city', 'siret']),
                'projects' => $client->projects->map(fn (ClientProject $project): array => [
                    ...$project->only(['id', 'name', 'type', 'status']),
                    'quotes' => $project->quotes->whereNull('archived_at')->map(fn (Quote $quote): array => ['id' => $quote->id, 'number' => $quote->number, 'status' => $quote->status, 'editable' => $quote->finalized_at === null, 'total_ttc' => CommercialCalculator::euros($quote->totals['initial']['ttc'] ?? 0)])->values(),
                    'open_tasks' => $project->tasks->map(fn (AdminTask $task): array => $task->only(['id', 'title', 'details']))->values(),
                ])->values(),
            ])->values(),
            'prospects' => $leads->map(fn (Lead $lead): array => [...$lead->only(['id', 'firstname', 'lastname', 'company', 'email', 'phone', 'project_type', 'budget', 'desired_date', 'status']), 'description' => Str::limit((string) $lead->description, 1500)])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function createClient(array $input): array
    {
        $data = $this->clientData($input, true);
        $client = Client::create($data);

        return $this->ok(['client_id' => $client->id], 'Fiche client créée : '.$client->name, route('admin.clients'));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function updateClient(array $input): array
    {
        $client = Client::find((int) ($input['client_id'] ?? 0)) ?? throw ValidationException::withMessages(['client_id' => 'Client introuvable.']);
        $client->update($this->clientData($input, false));

        return $this->ok(['client_id' => $client->id, 'updated' => true], 'Fiche client complétée : '.$client->name, route('admin.clients'));
    }

    /** Same rules as the client form of the commercial directory.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function clientData(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $rules = ['audience' => [$required, 'in:professional,consumer'], 'name' => $required.'|string|max:200', 'email' => $required.'|email|max:255', 'contact' => 'nullable|string|max:200', 'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:2000'];
        foreach (['firstname', 'lastname', 'postal_code', 'city', 'siret'] as $key) {
            $rules[$key] = 'nullable|string|max:2000';
        }
        foreach (['company', 'country', 'vat_number', 'signatory', 'signatory_role'] as $key) {
            $rules[$key] = 'nullable|string|max:255';
        }
        $data = Validator::make($input, $rules)->validate();

        return array_map(fn ($value) => is_string($value) ? trim($value) : $value, $data);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function createProject(array $input): array
    {
        $data = Validator::make($input, [
            'client_id' => 'required|integer|exists:clients,id', 'lead_id' => 'nullable|integer|exists:leads,id',
            'name' => 'required|string|max:200', 'type' => ['required', Rule::in(ClientProject::TYPES)],
            'description' => 'nullable|string|max:20000', 'due_on' => 'nullable|date|after_or_equal:today',
            'website_url' => 'nullable|string|url:http,https|max:255', 'domain' => 'nullable|string|max:255',
        ])->validate();
        $project = DB::transaction(function () use ($data): ClientProject {
            $project = ClientProject::create([...$data, 'status' => 'quote_to_prepare']);
            ProjectEvent::record($project->id, 'Projet créé par l’assistant'.(isset($data['lead_id']) ? ' depuis le prospect #'.$data['lead_id'] : ''));

            return $project;
        });

        return $this->ok(['project_id' => $project->id], 'Projet créé : '.$project->name, route('admin.client-projects.show', ['clientProject' => $project->id]));
    }

    /**
     * Builds the lines exactly as the quote editor does when a catalog service is picked,
     * then lets the commercial calculator validate and total them.
     *
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function saveQuote(array $input, ?int $quoteId): array
    {
        Validator::make($input, [
            'items' => 'required|array|min:1|max:100', 'items.*' => 'array',
            'discount_percent' => 'nullable|numeric|between:0,100', 'deposit_percent' => 'nullable|numeric|between:0,100',
            'estimated_delay' => 'nullable|string|max:2000',
        ])->validate();
        $settings = CommercialSetting::current();
        $items = [];
        $offers = 0;
        foreach ($input['items'] as $position => $line) {
            $item = ['contract_rules' => [], 'contract_included' => true, 'category' => '', 'pricing_mode' => 'fixed', 'payee' => 'codenyr', 'name' => '', 'description' => '', 'unit' => 'forfait', 'quantity' => '1', 'price' => '', 'discount_type' => 'percent', 'discount' => '0', 'vat' => (string) ($settings['vat_enabled'] ? $settings['vat_rate'] : '0'), 'frequency' => 'once', 'optional' => false, 'selected' => true];
            if (! empty($line['service_id'])) {
                $service = Service::where('active', true)->whereNull('archived_at')->find((int) $line['service_id'])
                    ?? throw ValidationException::withMessages(['items' => 'Ligne '.($position + 1).' : prestation #'.$line['service_id'].' introuvable ou inactive.']);
                $offers += str_starts_with($service->price_key ?? '', 'offer_') ? 1 : 0;
                $item = [...$item, 'contract_rules' => ContractCatalogRules::forService($service), 'contract_key' => $service->price_key ?: ($service->catalog_key ?: 'service_'.$service->id), 'category' => $service->category, 'pricing_mode' => $service->pricing_mode, 'service_id' => $service->id, 'name' => $service->name, 'description' => $service->description ?? '', 'unit' => $service->unit, 'price' => $service->pricing_mode === 'quote' ? '' : CommercialCalculator::input($service->currentPrice()), 'frequency' => $service->frequency];
                if (blank($input['estimated_delay'] ?? null) && filled($service->contract_rules['delivery_days'] ?? null)) {
                    $input['estimated_delay'] = $service->contract_rules['delivery_days'].' jours';
                }
            } elseif (isset($line['frequency'])) {
                $item['frequency'] = (string) $line['frequency'];
            }
            foreach (['name', 'description'] as $key) {
                if (filled($line[$key] ?? null)) {
                    $item[$key] = (string) $line[$key];
                }
            }
            if (isset($line['quantity'])) {
                $item['quantity'] = $this->amount($line['quantity']);
            }
            if (isset($line['unit_price'])) {
                $item['price'] = $this->amount($line['unit_price']);
            }
            if ($item['price'] === '') {
                throw ValidationException::withMessages(['items' => 'Ligne '.($position + 1).' (« '.($item['name'] ?: 'sans nom').' ») : indiquez un prix unitaire.']);
            }
            $item['optional'] = (bool) ($line['optional'] ?? false);
            $item['selected'] = ! $item['optional'];
            $items[] = $item;
        }
        if ($offers > 1) {
            throw ValidationException::withMessages(['items' => 'Un devis ne contient qu’une seule offre principale (offer_*).']);
        }

        $calculator = app(CommercialCalculator::class);
        $items = $calculator->normalize($items, true);
        $discount = CommercialCalculator::decimal($this->amount($input['discount_percent'] ?? 0));
        $deposit = CommercialCalculator::decimal($this->amount($input['deposit_percent'] ?? $settings['deposit_percent']));
        $depositType = $deposit > 0 ? 'percent' : 'none';
        $totals = $calculator->calculate($items, 'percent', $discount, $depositType, $deposit);

        $quote = DB::transaction(function () use ($input, $quoteId, $items, $totals, $discount, $deposit, $depositType, $settings): Quote {
            $billing = app(CommercialBilling::class);
            if ($quoteId) {
                $quote = Quote::lockForUpdate()->find($quoteId) ?? throw ValidationException::withMessages(['quote_id' => 'Devis introuvable.']);
                if ($quote->finalized_at || $quote->archived_at) {
                    throw ValidationException::withMessages(['quote_id' => 'Ce devis est déjà envoyé ou archivé : il ne peut plus être modifié. Proposez à l’administrateur de le dupliquer.']);
                }
                $project = ClientProject::lockForUpdate()->findOrFail($quote->client_project_id);
            } else {
                $project = ClientProject::lockForUpdate()->with('client')->find((int) ($input['project_id'] ?? 0)) ?? throw ValidationException::withMessages(['project_id' => 'Projet introuvable.']);
                $quote = new Quote(['client_project_id' => $project->id, 'status' => 'draft', 'number' => $billing->number('quote'), 'issued_on' => today(), 'due_on' => today()->addDays((int) $settings['validity_days']), 'conditions' => $settings['quote_mention'], 'snapshot' => $billing->snapshot($project)]);
            }
            if ($project->archived_at) {
                throw ValidationException::withMessages(['project_id' => 'Ce projet est archivé.']);
            }
            $snapshot = $quote->snapshot;
            $snapshot['seller']['vat_enabled'] = array_any($items, fn (array $item): bool => $item['vat_basis'] > 0);
            $quote->fill(['items' => $items, 'totals' => $totals, 'discount_type' => 'percent', 'discount_value' => $discount, 'deposit_type' => $depositType, 'deposit_value' => $deposit, 'estimated_delay' => $input['estimated_delay'] ?? $quote->estimated_delay, 'snapshot' => $snapshot])->save();
            ProjectEvent::record($project->id, ($quoteId ? 'Devis modifié par l’assistant : ' : 'Devis préparé par l’assistant : ').$quote->number);

            return $quote;
        });

        $recurring = [];
        foreach ($totals['recurring'] as $frequency => $group) {
            if ($group['ttc'] > 0) {
                $recurring[$frequency] = CommercialCalculator::euros($group['ttc']);
            }
        }

        return $this->ok([
            'quote_id' => $quote->id, 'number' => $quote->number, 'status' => 'brouillon',
            'total_ht' => CommercialCalculator::euros($totals['initial']['ht']), 'total_ttc' => CommercialCalculator::euros($totals['initial']['ttc']),
            'deposit' => CommercialCalculator::euros($totals['deposit']), 'recurring_ttc' => $recurring,
        ], ($quoteId ? 'Devis modifié : ' : 'Devis préparé : ').$quote->number.' ('.CommercialCalculator::euros($totals['initial']['ttc']).($settings['vat_enabled'] ? ' TTC' : '').')', route('admin.billing.quotes.edit', ['id' => $quote->id]));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function addTask(array $input): array
    {
        $data = Validator::make($input, ['title' => 'required|string|max:200', 'details' => 'nullable|string|max:2000', 'category' => ['required', Rule::in(AdminTask::CATEGORIES)], 'project_id' => 'nullable|integer|exists:client_projects,id'])->validate();
        $task = AdminTask::create(['title' => $data['title'], 'category' => $data['category'], 'details' => ($data['details'] ?? null) ?: null, 'client_project_id' => $data['project_id'] ?? null, 'position' => (int) AdminTask::max('position') + 1]);

        return $this->ok(['task_id' => $task->id], 'Tâche ajoutée : '.$task->title, route('admin.tasks'));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function completeTask(array $input): array
    {
        $task = AdminTask::find((int) ($input['task_id'] ?? 0)) ?? throw ValidationException::withMessages(['task_id' => 'Tâche introuvable.']);
        $task->update(['done_at' => $task->done_at ?? now()]);
        if ($task->client_project_id) {
            ProjectEvent::record($task->client_project_id, 'Tâche cochée par l’assistant : '.$task->title);
        }

        return $this->ok(['done' => true], 'Tâche cochée : '.$task->title, route('admin.tasks'));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function addNote(array $input): array
    {
        $data = Validator::make($input, ['project_id' => 'required|integer|exists:client_projects,id', 'content' => 'required|string|max:20000'])->validate();
        ProjectNote::create(['client_project_id' => $data['project_id'], 'user_id' => auth()->id(), 'content' => $data['content']]);

        return $this->ok(['created' => true], 'Note ajoutée au dossier', route('admin.client-projects.show', ['clientProject' => $data['project_id']]));
    }

    /**
     * Drafts the CGV and the contract and hands the agent what it needs to check them:
     * the application's blocking checks, the references they took up, and their text.
     *
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function prepareLegalDocuments(array $input): array
    {
        $project = ClientProject::with('client')->find((int) ($input['project_id'] ?? 0)) ?? throw ValidationException::withMessages(['project_id' => 'Projet introuvable.']);
        if ($project->archived_at) {
            throw ValidationException::withMessages(['project_id' => 'Ce projet est archivé.']);
        }
        $drafts = app(ProjectAutopilot::class)->draftKit($project);
        $documents = [];
        foreach ($drafts as $draft) {
            $document = Document::findOrFail($draft['id']);
            $snapshot = $document->snapshot ?? [];
            $quote = $snapshot['quote'] ?? [];
            $documents[] = [
                ...$draft,
                'state' => $draft['official'] ? 'déjà officiel (finalisé), non modifiable' : 'brouillon à relire',
                'references' => [
                    'audience' => $snapshot['audience'] ?? null,
                    'seller' => array_intersect_key($snapshot['seller'] ?? [], array_flip(['legal_name', 'legal_status', 'address', 'registration', 'siren', 'email', 'tax_mention', 'vat_enabled', 'signatory', 'signatory_role'])),
                    'client' => array_intersect_key($snapshot['client'] ?? [], array_flip(['name', 'company', 'address', 'postal_code', 'city', 'country', 'registration', 'siret', 'vat_number', 'email', 'signatory', 'signatory_role'])),
                    'quote' => ['number' => $quote['number'] ?? null, 'status' => $quote['status'] ?? null, 'estimated_delay' => $quote['estimated_delay'] ?? null, 'conditions' => $quote['conditions'] ?? null,
                        'ht' => CommercialCalculator::euros($quote['totals']['initial']['ht'] ?? 0), 'ttc' => CommercialCalculator::euros($quote['totals']['initial']['ttc'] ?? 0), 'deposit' => CommercialCalculator::euros($quote['totals']['deposit'] ?? 0)],
                    'project' => array_intersect_key($snapshot['project'] ?? [], array_flip(['name', 'starts_on', 'due_on', 'domain', 'host'])),
                    'terms_version' => isset($snapshot['terms']) ? ($snapshot['terms']['name'] ?? '').' v'.($snapshot['terms']['revision'] ?? '').' du '.($snapshot['terms']['effective_on'] ?? '') : null,
                    'contract_fields' => array_intersect_key($snapshot['fields'] ?? [], array_flip(['object', 'deliverables', 'exclusions', 'ip_mode', 'domain_mode', 'hosting_mode', 'maintenance_mode', 'review_days', 'revision_rounds', 'privacy_processing'])),
                ],
                'text' => Str::limit((string) $document->content, 15000),
            ];
        }

        return $this->ok(['project_id' => $project->id, 'documents' => $documents], 'Brouillons des CGV et du contrat préparés pour relecture', route('admin.client-projects.show', ['clientProject' => $project->id]));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function recordLegalReview(array $input): array
    {
        $data = Validator::make($input, ['project_id' => 'required|integer|exists:client_projects,id', 'summary' => 'required|string|max:3000', 'points' => 'nullable|array|max:30', 'points.*' => 'string|max:500'])->validate();
        $drafts = Document::where('client_project_id', $data['project_id'])->whereIn('type', ['terms', 'contract'])->whereNull('original_id')->whereNull('archived_at')->whereNull('finalized_at')->get();
        if ($drafts->isEmpty()) {
            throw ValidationException::withMessages(['project_id' => 'Aucun brouillon de CGV ou de contrat à relire : appelle d’abord prepare_legal_documents.']);
        }
        $review = ['summary' => $data['summary'], 'points' => array_values($data['points'] ?? []), 'at' => now()->toIso8601String()];
        DB::transaction(function () use ($drafts, $review, $data): void {
            foreach ($drafts as $draft) {
                $draft->update(['snapshot' => [...($draft->snapshot ?? []), 'assistant_review' => $review]]);
            }
            ProjectNote::create(['client_project_id' => $data['project_id'], 'user_id' => auth()->id(), 'content' => "Relecture des brouillons CGV et contrat par l’assistant :\n".$review['summary'].($review['points'] ? "\nÀ vérifier :\n- ".implode("\n- ", $review['points']) : '')]);
        });

        return $this->ok(['recorded' => true], 'Relecture des brouillons enregistrée'.($review['points'] ? ' ('.count($review['points']).' point(s) à vérifier)' : ''), route('admin.client-projects.show', ['clientProject' => $data['project_id']]));
    }

    /** Model numbers (1200, 1200.5, "1 200,50") as the decimal strings the calculator expects. */
    private function amount(mixed $value): string
    {
        return is_float($value) ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') : trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return ToolResult
     */
    private function ok(array $data, ?string $action = null, ?string $url = null): array
    {
        return ['content' => (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'is_error' => false, 'actions' => $action ? [['text' => $action, 'url' => $url]] : []];
    }

    /** @return ToolResult */
    private function error(string $message): array
    {
        return ['content' => $message, 'is_error' => true, 'actions' => []];
    }
}
