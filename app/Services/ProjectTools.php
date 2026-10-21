<?php

namespace App\Services;

use App\Models\AdminTask;
use App\Models\AssistantConversation;
use App\Models\ClientProject;
use App\Models\CommercialSetting;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\MaintenanceContract;
use App\Models\ProjectEvent;
use App\Models\ProjectNote;
use App\Models\Quote;
use App\Support\ProjectWebsite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What the project assistant may do: read every project file (progress, documents,
 * billing, notes) and keep it up to date (status, dates, notes, follow-up tasks).
 * Quote answers, documents, invoices and payments stay one click away in the dossier,
 * done by the admin.
 *
 * @phpstan-import-type ToolResult from AssistantToolset
 * @phpstan-import-type ToolSpec from AssistantToolset
 */
class ProjectTools implements AssistantToolset
{
    /** Pilot steps (ProjectAutopilot::STEPS) a status needs before it can be set. */
    private const STATUS_REQUIRES = [
        'quote_sent' => ['sent'],
        'quote_accepted' => ['accepted'],
        'waiting_deposit' => ['accepted'],
        'ready' => ['accepted', 'signed', 'deposit'],
        'development' => ['accepted', 'signed', 'deposit'],
        'validation' => ['accepted', 'signed', 'deposit'],
        'launch' => ['accepted', 'signed', 'deposit'],
        'completed' => ['accepted', 'signed', 'deposit'],
        'maintenance' => ['accepted', 'signed', 'deposit'],
    ];

    public function __construct(private AssistantTools $shared, private ProjectAutopilot $pilot) {}

    public function prompt(): string
    {
        $statuses = collect(ClientProject::STATUSES)->map(fn (string $label, string $key): string => "{$key} = {$label}")->implode(', ');
        $steps = implode(' → ', ProjectAutopilot::STEPS);

        return <<<PROMPT
        Tu es le chef de projet de Codenyr, micro-entreprise française de création de sites web. Tu travailles pour son fondateur, seul utilisateur de cette interface, qui réalise lui-même les sites. Ton rôle : savoir où en est chaque projet, décider de ce qu’il faut faire et dans quel ordre, faire exécuter la paperasse, et tenir les dossiers à jour à partir de ce qu’il te raconte.

        Ton équipe : l’assistant administratif (outil delegate_to_admin). Il crée les fiches client, les projets et les devis en brouillon (ou les modifie tant qu’ils ne sont pas envoyés), à partir du catalogue et des tarifs. Il prépare aussi les brouillons des CGV et du contrat d’un projet dont le devis est accepté, complète les informations manquantes et vérifie leur conformité et leurs références. Toute la paperasse de ce type lui revient : tu ne la fais pas toi-même.
        - Dès que le dossier montre le devis accepté et que les documents de démarrage n’existent pas encore, propose de faire préparer les brouillons des CGV et du contrat (ou délègue-le si l’utilisateur le demande). Ensuite, dis-lui de relire les deux PDF dans le Pilote du dossier, avec les points relevés, puis de cliquer sur « Valider et créer la version officielle ».
        - Donne-lui une consigne complète et autonome : il ne voit pas ta conversation et ne peut pas poser de questions à l’utilisateur. Cite le client, les prestations, les quantités, les prix ou remises convenus, les dates, et le project_id quand le projet existe (son dossier lui est alors joint).
        - Délègue ce que l’utilisateur demande ou ce qui découle directement de sa demande (« le client veut une page boutique en plus » → devis complémentaire). Pour une initiative de ta part, propose-la d’abord et attends son accord.
        - Lis son compte rendu : s’il manque une information, demande-la à l’utilisateur puis renvoie une consigne. Résume ensuite ce qui a été fait, avec le numéro et le montant des devis, les tâches qu’il a ouvertes ou cochées, et les questions encore en suspens.

        Ce que tu fais :
        - « Fais le point », « qu’est-ce que je fais aujourd’hui ? » : appelle list_projects, puis donne une liste courte et priorisée. D’abord ce qui bloque l’argent ou les délais (facture échue, acompte non reçu, livraison proche ou dépassée, devis sans réponse), puis la prochaine étape de chaque projet actif. Une ligne par projet, avec une action concrète.
        - Questions sur un projet précis : appelle get_project et réponds à partir de son dossier (avancement, documents, factures, notes, tâches ouvertes, historique).
        - Quand l’utilisateur raconte une avancée (« j’ai fini la maquette », « le client valide », « il veut décaler à décembre », « il a appelé pour… ») : appelle d’abord get_project, puis mets à jour le projet (update_project : statut, dates, description) et garde toujours une trace dans une note au dossier (add_project_note) : le dossier est la mémoire commune de l’équipe. Ajoute une tâche (add_task, avec project_id) pour toute action à ne pas oublier.
        - Le dossier fait foi : « progress » et « next_step » sont calculés à partir des vrais devis, signatures et paiements enregistrés. Si l’utilisateur dit qu’une étape est faite mais que le dossier ne la montre pas (devis accepté, CGV et contrat signés, acompte encaissé), ne fais pas comme si : dis-lui ce qui manque et où l’enregistrer (Pilote du dossier). Ne lui dis jamais qu’il peut démarrer le développement tant que le dossier n’indique pas les signatures et l’acompte.
        - Tâches : après chaque mise à jour, regarde les tâches ouvertes du projet (open_tasks) et coche (complete_task) celles que la nouvelle situation règle ; dans le doute, demande. La liste « À faire » (list_tasks) fait aussi partie du point quotidien.
        - Signale de toi-même une incohérence repérée dans un dossier (date de livraison passée sans statut « Terminé », projet en développement sans acompte reçu, etc.).

        Parcours d’un projet dans l’application : {$steps}.
        Statuts possibles : {$statuses}.

        Règles :
        - Ni toi ni l’assistant administratif ne pouvez envoyer, accepter, facturer, encaisser ou générer de documents (CGV, contrat, factures). Ces actions se font en un clic depuis le « Pilote du dossier » du projet : indique-le et donne le lien du dossier.
        - Pour le statut « Terminé », vérifie avec l’utilisateur que le site est bien livré avant de le passer.
        - N’invente aucune information : si un dossier ne dit rien, dis-le.
        - Si un outil renvoie une erreur, corrige la demande et réessaie ; si c’est impossible, explique pourquoi en une phrase.
        - Réponds en français, de façon brève et concrète, sans jargon technique. Pas de titres ni de tableaux : quelques phrases ou une courte liste.
        - Cite les devis et factures par leur numéro exact (DEV-…, FAC-…) et les projets par leur nom exact : l’interface en fait des liens, y compris vers le Pilote du dossier quand tu nommes le projet. N’écris pas d’adresse web toi-même.
        PROMPT;
    }

    public function definitions(): array
    {
        return [
            $this->tool('list_projects', 'Liste les projets avec leur client, statut, date de livraison, prochaine étape et alertes (retards, impayés, signatures manquantes). Par défaut, seulement les projets en cours.', [
                'query' => ['type' => 'string', 'description' => 'Filtre sur le nom du projet ou du client.'],
                'include_finished' => ['type' => 'boolean', 'description' => 'Inclure aussi les projets terminés, annulés et archivés.'],
            ], []),
            $this->tool('get_project', 'Dossier complet d’un projet : description, dates, avancement étape par étape, devis, factures et règlements, documents, maintenance, notes et historique récent.', [
                'project_id' => ['type' => 'integer'],
            ], ['project_id']),
            $this->tool('update_project', 'Met à jour un projet. Seuls les champs fournis changent.', [
                'project_id' => ['type' => 'integer'],
                'status' => ['type' => 'string', 'enum' => array_keys(ClientProject::STATUSES)],
                'starts_on' => ['type' => 'string', 'description' => 'AAAA-MM-JJ, ou chaîne vide pour effacer.'],
                'due_on' => ['type' => 'string', 'description' => 'Livraison prévue, AAAA-MM-JJ, ou chaîne vide pour effacer.'],
                'description' => ['type' => 'string', 'description' => 'Remplace la description du besoin.'],
                'website_url' => ['type' => 'string'], 'domain' => ['type' => 'string'], 'host' => ['type' => 'string', 'description' => 'Hébergeur.'],
                'confirmed_outside_app' => ['type' => 'boolean', 'description' => 'Uniquement si l’utilisateur confirme explicitement qu’une étape manquante du dossier (signature, acompte…) est réglée hors de l’application.'],
            ], ['project_id']),
            $this->tool('add_project_note', 'Ajoute une note interne au dossier d’un projet (échange avec le client, décision, avancée).', [
                'project_id' => ['type' => 'integer'], 'content' => ['type' => 'string'],
                'pinned' => ['type' => 'boolean', 'description' => 'Épingler une information importante en haut du dossier.'],
            ], ['project_id', 'content']),
            ...array_values(array_filter($this->shared->definitions(), fn (array $tool): bool => in_array($tool['name'], ['add_task', 'complete_task'], true))),
            $this->tool('list_tasks', 'Liste les tâches ouvertes de l’onglet « À faire », avec le projet auquel chacune se rattache.', [
                'category' => ['type' => 'string', 'enum' => AdminTask::CATEGORIES, 'description' => 'Facultatif : une seule catégorie.'],
            ], []),
            $this->tool('delegate_to_admin', 'Confie une tâche administrative à l’assistant administratif : créer ou compléter une fiche client, créer un projet, préparer ou modifier un devis en brouillon, préparer et vérifier les brouillons des CGV et du contrat. Il l’exécute aussitôt et renvoie son compte rendu.', [
                'instructions' => ['type' => 'string', 'description' => 'Consigne complète et autonome : client, prestations, quantités, prix convenus, dates.'],
                'project_id' => ['type' => 'integer', 'description' => 'Projet concerné : son dossier complet est joint à la consigne.'],
            ], ['instructions']),
        ];
    }

    public function run(string $name, array $input): array
    {
        try {
            return match ($name) {
                'list_projects' => $this->ok($this->listProjects((string) ($input['query'] ?? ''), (bool) ($input['include_finished'] ?? false))),
                'get_project' => $this->ok($this->getProject($this->project($input))),
                'update_project' => $this->updateProject($input),
                'add_project_note' => $this->addNote($input),
                'add_task', 'complete_task' => $this->shared->run($name, $input),
                'list_tasks' => $this->ok(AdminTask::open()->with('project')->when(isset($input['category']), fn ($q) => $q->where('category', $input['category']))->orderBy('position')->get()->reject->isDone()
                    ->map(fn (AdminTask $task): array => [...$task->only(['id', 'category', 'title', 'details']), 'project' => $task->project ? ['id' => $task->project->id, 'name' => $task->project->name] : null])->values()->all()),
                'delegate_to_admin' => $this->delegate($input),
                default => $this->error('Outil inconnu : '.$name),
            };
        } catch (ValidationException $e) {
            return $this->error('Refusé par l’application : '.collect($e->errors())->flatten()->implode(' '));
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function listProjects(string $query, bool $finished): array
    {
        $settings = CommercialSetting::current();
        $term = '%'.mb_substr(trim($query), 0, 100).'%';
        $projects = ClientProject::with(['client', 'quotes', 'invoices.payments', 'documents', 'maintenance', 'tasks' => fn ($q) => $q->whereNull('done_at')])
            ->when(! $finished, fn ($q) => $q->whereNull('archived_at')->whereNotIn('status', ['completed', 'cancelled']))
            ->when(trim($query) !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhereHas('client', fn ($q) => $q->where('name', 'like', $term))))
            ->orderByRaw('due_on is null')->orderBy('due_on')->limit(40)->get();

        return $projects->map(fn (ClientProject $project): array => [
            'id' => $project->id, 'name' => $project->name, 'client' => $project->client->name, 'type' => $project->type,
            'status' => ClientProject::STATUSES[$project->status] ?? $project->status,
            'due_on' => $project->due_on?->format('Y-m-d'), 'archived' => $project->archived_at !== null,
            'next_step' => ($step = $this->pilot->current($project)) ? ProjectAutopilot::STEPS[$step] : 'Projet soldé',
            'alerts' => $project->alerts($settings),
            'open_tasks' => $project->tasks->map(fn (AdminTask $task): array => $task->only(['id', 'title']))->values()->all(),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function getProject(ClientProject $project): array
    {
        $project->load(['client', 'quotes', 'invoices.payments', 'documents', 'maintenance', 'notes', 'tasks' => fn ($q) => $q->whereNull('done_at'), 'events' => fn ($q) => $q->latest()->limit(15)]);
        $progress = $this->pilot->progress($project);
        $next = $this->pilot->current($project);

        return [
            ...$project->only(['id', 'name', 'type', 'description', 'website_url', 'domain', 'host']),
            'url' => route('admin.client-projects.show', ['clientProject' => $project->id]),
            'status' => ClientProject::STATUSES[$project->status] ?? $project->status,
            'starts_on' => $project->starts_on?->format('Y-m-d'), 'due_on' => $project->due_on?->format('Y-m-d'),
            'archived' => $project->archived_at !== null,
            'client' => $project->client->only(['id', 'name', 'audience', 'contact', 'email', 'phone']),
            // Computed from the real quotes, signed documents and payments: the reference for what is done.
            'progress' => collect($progress)->mapWithKeys(fn (bool $done, string $step): array => [ProjectAutopilot::STEPS[$step] => $done])->all(),
            'next_step' => $next ? ProjectAutopilot::STEPS[$next] : 'Projet soldé',
            'alerts' => $project->alerts(),
            'open_tasks' => $project->tasks->map(fn (AdminTask $task): array => $task->only(['id', 'title', 'details']))->values()->all(),
            'quotes' => $project->quotes->whereNull('archived_at')->map(fn (Quote $quote): array => ['number' => $quote->number, 'status' => Quote::STATUSES[$quote->status] ?? $quote->status, 'total_ttc' => CommercialCalculator::euros($quote->totals['initial']['ttc'] ?? 0), 'sent_on' => $quote->sent_at?->format('Y-m-d')])->values()->all(),
            'invoices' => $project->invoices->whereNull('archived_at')->map(fn (Invoice $invoice): array => ['number' => $invoice->number ?? 'brouillon', 'type' => Invoice::TYPES[$invoice->invoice_type] ?? $invoice->invoice_type, 'state' => $invoice->paymentLabel(), 'total_ttc' => CommercialCalculator::euros($invoice->totals['initial']['ttc'] ?? 0), 'remaining' => CommercialCalculator::euros($invoice->remainingCents()), 'due_on' => $invoice->due_on->format('Y-m-d')])->values()->all(),
            'documents' => $project->documents->whereNull('archived_at')->map(fn (Document $document): array => ['name' => $document->name, 'type' => Document::TYPES[$document->type] ?? $document->type, 'finalized' => $document->finalized_at !== null, 'signed_copy' => $document->original_id !== null])->values()->all(),
            'maintenance' => $project->maintenance->where('status', '!=', 'archived')->map(fn (MaintenanceContract $contract): array => ['name' => $contract->name, 'amount' => CommercialCalculator::euros($contract->amount_cents), 'frequency' => $contract->frequency, 'status' => $contract->status])->values()->all(),
            'notes' => $project->notes->sortByDesc('created_at')->take(15)->map(fn (ProjectNote $note): array => ['date' => $note->created_at?->format('Y-m-d'), 'pinned' => $note->pinned, 'content' => Str::limit($note->content, 1500)])->values()->all(),
            'history' => $project->events->map(fn (ProjectEvent $event): string => $event->created_at?->format('Y-m-d').' — '.$event->description)->values()->all(),
        ];
    }

    /**
     * Same rules as the project form of the dossier.
     *
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function updateProject(array $input): array
    {
        $project = $this->project($input);
        if ($project->archived_at) {
            throw ValidationException::withMessages(['project_id' => 'Ce projet est archivé : restaurez-le depuis son dossier avant de le modifier.']);
        }
        $fields = array_intersect_key($input, array_flip(['status', 'starts_on', 'due_on', 'description', 'website_url', 'domain', 'host']));
        if (is_string($fields['website_url'] ?? null)) {
            $fields['website_url'] = ProjectWebsite::normalize($fields['website_url']);
        }
        foreach (['starts_on', 'due_on'] as $field) {
            if (array_key_exists($field, $fields)) {
                $fields[$field] = $fields[$field] ?: null;
            }
        }
        $dates = ['starts_on' => $project->starts_on?->format('Y-m-d'), 'due_on' => $project->due_on?->format('Y-m-d'), ...array_intersect_key($fields, array_flip(['starts_on', 'due_on']))];
        Validator::make([...$fields, 'dates' => $dates], [
            'status' => ['sometimes', Rule::in(array_keys(ClientProject::STATUSES))],
            'starts_on' => 'nullable|date', 'due_on' => 'nullable|date',
            'dates.due_on' => $dates['starts_on'] ? 'nullable|date|after_or_equal:dates.starts_on' : 'nullable',
            'description' => 'sometimes|nullable|string|max:20000', 'domain' => 'nullable|string|max:255', 'host' => 'nullable|string|max:255',
            'website_url' => 'nullable|string|url:http,https|max:255',
        ], ['dates.due_on.after_or_equal' => 'La date de livraison doit suivre la date de début.'])->validate();
        if ($fields === []) {
            throw ValidationException::withMessages(['project_id' => 'Aucun champ à modifier.']);
        }
        $this->ensureReachable($project, (string) ($fields['status'] ?? ''), (bool) ($input['confirmed_outside_app'] ?? false));

        $delivered = ($fields['status'] ?? null) === 'completed' && $project->status !== 'completed';
        $changes = $delivered ? array_diff_key($fields, ['status' => true]) : $fields;
        if ($changes !== []) {
            DB::transaction(function () use ($project, $changes): void {
                $project->update($changes);
                ProjectEvent::record($project->id, 'Projet mis à jour par l’assistant — '.ClientProject::STATUSES[$project->status]);
            });
        }
        if ($delivered) {
            // Same path as the dossier's "Site livré" button, which also dates the delivery on the lead.
            $this->pilot->markDelivered($project);
        }
        $project->refresh();
        $next = $this->pilot->current($project);

        return $this->ok([
            'project_id' => $project->id, 'status' => ClientProject::STATUSES[$project->status], 'due_on' => $project->due_on?->format('Y-m-d'),
            'next_step' => $next ? ProjectAutopilot::STEPS[$next] : 'Projet soldé',
            // Tasks this change may have settled: close them, or ask the user.
            'open_tasks' => $project->tasks()->whereNull('done_at')->get(['id', 'title'])->toArray(),
        ], 'Projet mis à jour : '.$project->name.' ('.ClientProject::STATUSES[$project->status].')', route('admin.client-projects.show', ['clientProject' => $project->id]));
    }

    /**
     * A status may not claim more than the dossier shows (accepted quote, signed CGV and
     * contract, deposit received): those steps are recorded in the pilot, by the admin.
     * Only an explicit confirmation that it was settled outside the app lifts the check.
     */
    private function ensureReachable(ClientProject $project, string $status, bool $confirmed): void
    {
        $required = self::STATUS_REQUIRES[$status] ?? [];
        if ($required === [] || $confirmed) {
            return;
        }
        $progress = $this->pilot->progress($project->fresh(['quotes', 'invoices.payments', 'documents']) ?? $project);
        // A step missing from the progress (deposit when none is due) does not apply.
        $missing = array_values(array_filter($required, fn (string $step): bool => ($progress[$step] ?? true) === false));
        if ($missing !== []) {
            throw ValidationException::withMessages(['status' => '« '.ClientProject::STATUSES[$status].' » n’est pas encore possible : le dossier n’indique pas '
                .implode(', ', array_map(fn (string $step): string => '« '.ProjectAutopilot::STEPS[$step].' »', $missing))
                .'. Ces étapes s’enregistrent dans le Pilote du dossier ('.route('admin.client-projects.show', ['clientProject' => $project->id]).'). Dis à l’utilisateur ce qu’il reste à y faire. Ne relance avec confirmed_outside_app que s’il confirme explicitement que c’est réglé hors de l’application.']);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function addNote(array $input): array
    {
        $data = Validator::make($input, ['project_id' => 'required|integer|exists:client_projects,id', 'content' => 'required|string|max:20000', 'pinned' => 'sometimes|boolean'])->validate();
        DB::transaction(function () use ($data): void {
            ProjectNote::create(['client_project_id' => $data['project_id'], 'user_id' => auth()->id(), 'content' => $data['content'], 'pinned' => $data['pinned'] ?? false]);
            ProjectEvent::record($data['project_id'], 'Note ajoutée par l’assistant');
        });

        return $this->ok(['created' => true], 'Note ajoutée au dossier', route('admin.client-projects.show', ['clientProject' => $data['project_id']]));
    }

    /**
     * Starts the admin assistant on a request of its own, in a conversation of its own (kept
     * in its history for review). AdminAssistant advances it step by step, then hands back
     * delegationReport(). The admin assistant has no delegation tool, so it cannot call back.
     *
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    private function delegate(array $input): array
    {
        $data = Validator::make($input, ['instructions' => 'required|string|max:10000', 'project_id' => 'nullable|integer|exists:client_projects,id'])->validate();
        $context = isset($data['project_id'])
            ? "\n\nDossier actuel du projet, pour information :\n".json_encode($this->getProject(ClientProject::findOrFail((int) $data['project_id'])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '';
        $conversation = AssistantConversation::create(['user_id' => auth()->id(), 'agent' => 'admin', 'provider' => AdminAssistant::provider(), 'title' => Str::limit('Demande du chef de projet : '.$data['instructions'], 80)]);
        app(AdminAssistant::class)->start($conversation,
            "Demande du chef de projet. L’utilisateur ne suit pas cet échange : ne lui pose aucune question. S’il manque une information indispensable pour une partie de la demande, ne crée rien pour cette partie et indique précisément ce qui manque dans ton compte rendu.\n\n".$data['instructions'].$context,
            'Demande du chef de projet : '.$data['instructions'],
        );

        return ['content' => '', 'is_error' => false, 'actions' => [], 'delegate' => $conversation->id];
    }

    /**
     * What the admin assistant did on a delegated request, once it has finished.
     *
     * @return ToolResult
     */
    public function delegationReport(AssistantConversation $conversation): array
    {
        $log = collect($conversation->log)->slice(1);
        $actions = [];
        foreach ($log->where('role', 'action') as $entry) {
            $actions[] = ['text' => 'Assistant administratif — '.$entry['text'], 'url' => isset($entry['url']) ? (string) $entry['url'] : null];
        }

        return [
            'content' => (string) json_encode([
                'report' => $log->where('role', 'assistant')->pluck('text')->implode("\n\n") ?: 'Aucun compte rendu.',
                'done' => array_column($actions, 'text'),
                'errors' => $log->where('role', 'error')->pluck('text')->values()->all(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_error' => false,
            'actions' => $actions,
        ];
    }

    /** @param  array<string, mixed>  $input */
    private function project(array $input): ClientProject
    {
        return ClientProject::find((int) ($input['project_id'] ?? 0)) ?? throw ValidationException::withMessages(['project_id' => 'Projet introuvable.']);
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

    /**
     * @param  array<mixed>  $data
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
