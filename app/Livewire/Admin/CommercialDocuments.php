<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\ClientProject;
use App\Models\CommercialSetting;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\MaintenanceContract;
use App\Models\ProjectEvent;
use App\Models\Quote;
use App\Models\Service;
use App\Services\CommercialBilling;
use App\Services\CommercialCalculator;
use App\Services\CommercialPdf;
use App\Services\CustomerDocumentSharing;
use App\Services\DocumentAutofill;
use App\Services\LegalDocumentRules;
use App\Services\ProjectLegalDocuments;
use App\Support\TestMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use ZipArchive;

class CommercialDocuments extends AdminComponent
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $projectFilter = '';

    public string $clientFilter = '';

    public string $typeFilter = '';

    public string $yearFilter = '';

    public string $dateFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public string $mode = 'upload';

    public ?TemporaryUploadedFile $upload = null;

    /** @var array<string, mixed> */
    public array $form = [];

    #[Locked]
    public ?int $maintenanceId = null;

    #[Locked]
    public ?int $previewId = null;

    #[Locked]
    public ?int $revisionOf = null;

    public string $audience = '';

    /** @var array<string, mixed> */
    public array $contract = [];

    #[Locked]
    public string $generatedContent = '';

    #[Locked]
    public ?int $reviewId = null;

    #[Locked]
    public string $reviewHash = '';

    public bool $showAllFields = false;

    /** @var list<string> */
    #[Locked]
    public array $visibleLegalFields = [];

    private function autofillContext(): void
    {
        $project = ClientProject::whereKey($this->form['client_project_id'] ?: null)->first();
        $context = app(DocumentAutofill::class)->context($project, $this->form['document_date']);
        if ($project) {
            $this->form['client_id'] = $context['client_id'];
        }
        if (empty($this->form['quote_id'])) {
            $this->form['quote_id'] = $context['quote_id'];
        }
        if (! array_key_exists('terms_template_id', $this->contract)) {
            $this->contract['terms_template_id'] = $context['terms_template_id'];
        }
    }

    public function startDocument(string $type): void
    {
        abort_unless(in_array($type, ['contract', 'terms', 'other'], true), 422);
        $this->create();
        $this->form['type'] = $type;
        $this->updatedFormType();
    }

    public function updatedUpload(): void
    {
        if ($this->upload && ! filled($this->form['name'])) {
            $this->form['name'] = mb_substr(pathinfo($this->upload->getClientOriginalName(), PATHINFO_FILENAME), 0, 200);
        }
    }

    public function legalCandidate(): Document
    {
        $project = ClientProject::whereKey($this->form['client_project_id'] ?? null)->first();

        return new Document(['client_id' => $this->form['client_id'] ?: $project?->client_id, 'client_project_id' => $project?->id, 'quote_id' => ($this->form['quote_id'] ?? null) ?: null, 'invoice_id' => ($this->form['invoice_id'] ?? null) ?: null, 'type' => $this->form['type'] ?? 'terms', 'document_date' => ($this->form['document_date'] ?? '') ?: today(), 'contract_data' => $this->contract]);
    }

    public function updatedFormClientProjectId(): void
    {
        $this->contract = [];
        $this->form['quote_id'] = '';
        $this->form['invoice_id'] = '';
        $this->form['client_id'] = '';
        $this->autofillContext();
        $this->refreshLegal();
    }

    public function updatedFormType(): void
    {
        $this->resetValidation();
        $this->contract = [];
        if ($this->generatedContent !== '' || in_array($this->form['type'], ['terms', 'contract'])) {
            $this->form['name'] = '';
        }
        $this->generatedContent = '';
        $this->form['content'] = '';
        $this->form['template_id'] = '';
        $this->revisionOf = null;
        $this->maintenanceId = null;
        if (empty($this->form['original_id']) && in_array($this->form['type'], ['terms', 'contract'])) {
            $this->mode = 'generate';
        }
        $this->autofillContext();
        $this->refreshLegal();
    }

    public function updatedMode(): void
    {
        $this->resetValidation();
        $this->refreshLegal();
    }

    public function updatedContractTermsTemplateId(): void
    {
        foreach (array_keys($this->contract) as $key) {
            if (str_starts_with($key, 'clause_') || (str_starts_with($key, 'terms_') && $key !== 'terms_template_id')) {
                unset($this->contract[$key]);
            }
        }
        $this->refreshLegal();
    }

    public function updatedFormClientId(): void
    {
        foreach (array_keys($this->contract) as $key) {
            if (str_starts_with($key, 'client_')) {
                unset($this->contract[$key]);
            }
        }
        $this->refreshLegal();
    }

    public function updatedFormQuoteId(): void
    {
        foreach (array_keys($this->contract) as $key) {
            if (! str_starts_with($key, 'seller_') && ! str_starts_with($key, 'client_')) {
                unset($this->contract[$key]);
            }
        }
        $this->form['invoice_id'] = '';
        $this->refreshLegal();
    }

    private function refreshLegal(): void
    {
        if ($this->mode === 'generate' && in_array($this->form['type'] ?? '', ['terms', 'contract'])) {
            $this->prepareLegal();
        }
    }

    public function review(int $id): void
    {
        $document = Document::findOrFail($id);
        abort_unless(in_array($document->type, ['terms', 'contract']) && ! $document->original_id, 422);
        $this->reviewId = $id;
        $this->previewId = $id;
        $result = app(ProjectLegalDocuments::class)->prepare($document);
        $this->reviewHash = hash('sha256', json_encode($result['snapshot'], JSON_THROW_ON_ERROR));
    }

    public function draft(int $id): void
    {
        app(CommercialPdf::class)->document(Document::findOrFail($id), false);
        $this->previewId = $id;
        session()->flash('success', 'PDF brouillon généré.');
    }

    public function prepareLegal(): void
    {
        $this->validate(['form.type' => 'required|in:terms,contract', 'contract' => 'array', 'contract.*' => 'nullable|string|max:20000']);
        $result = app(ProjectLegalDocuments::class)->prepare($this->legalCandidate());
        $this->visibleLegalFields = array_values(array_unique(array_map(fn ($key): string => LegalDocumentRules::errorTarget((string) $key), array_keys($result['errors']))));
        foreach (LegalDocumentRules::formSources($this->form['type']) as $group => $definitions) {
            foreach ($definitions as $key => $label) {
                $value = $key === 'technologies' ? ($result['snapshot']['project']['contract_data']['technologies'] ?? '') : ($result['snapshot'][$group][$key] ?? '');
                if (in_array($key, ['starts_on', 'due_on']) && $value) {
                    $value = substr($value, 0, 10);
                }
                $this->contract[$group.'_'.$key] ??= (string) $value;
            }
        }
        foreach (LegalDocumentRules::CLAUSES as $key => $label) {
            $this->contract['clause_'.$key] ??= (string) ($result['snapshot']['seller']['clause_'.$key] ?? '');
        }
        foreach ($result['fields'] as $key => $value) {
            $this->contract[$key] ??= (string) $value;
        }
        $this->form['content'] = $this->generatedContent = $result['content'];
        $this->form['name'] = Document::TYPES[$this->form['type']].' — '.($result['snapshot']['project']['name'] ?? $result['snapshot']['client']['name'] ?? 'Codenyr');
        $this->mode = 'generate';
    }

    public function revise(int $id): void
    {
        $document = Document::findOrFail($id);
        abort_unless(in_array($document->type, ['terms', 'contract']) && $document->content && ! $document->original_id && ! $document->archived_at && ! $document->project?->archived_at, 422);
        $this->create();
        $this->revisionOf = $document->id;
        foreach (['name', 'type', 'client_id', 'client_project_id', 'description', 'notes', 'content', 'quote_id', 'invoice_id'] as $key) {
            $this->form[$key] = $document->$key ?? '';
        }
        $this->audience = $document->snapshot['audience'] ?? '';
        $this->contract = $document->contract_data ?? [];
        $this->generatedContent = $document->content;
        if (! $document->contract_data) {
            $this->contract['additional'] = $document->content;
        }
        $this->mode = 'generate';
        $this->prepareLegal();
    }

    public function mount(): void
    {
        $this->projectFilter = (string) (request()->integer('project') ?: '');
        if (request()->integer('edit')) {
            $this->revise(request()->integer('edit'));
        } elseif (request()->integer('sign')) {
            $this->sign(request()->integer('sign'));
        } elseif (request()->integer('preview')) {
            $this->preview(request()->integer('preview'));
        }
        if (request()->boolean('create')) {
            $this->create();
            $type = request()->query('type');
            if (is_string($type) && array_key_exists($type, Document::TYPES)) {
                $this->form['type'] = $type;
            }
            if (request()->query('mode') === 'generate') {
                $this->mode = 'generate';
                if (in_array($this->form['type'], ['terms', 'contract']) && $this->projectFilter) {
                    $project = ClientProject::findOrFail($this->projectFilter);
                    $this->autofillContext();
                    if (request()->integer('quote')) {
                        $quote = $project->quotes()->where('status', 'accepted')->whereNotNull('finalized_at')->whereKey(request()->integer('quote'))->firstOrFail();
                        $this->form['quote_id'] = $quote->id;
                    }
                    $this->form['name'] = Document::TYPES[$this->form['type']].' — '.$project->name;
                    $this->prepareLegal();
                }
            }
            if (request()->filled('maintenance')) {
                $maintenance = MaintenanceContract::where('client_project_id', $this->projectFilter)->findOrFail(request()->integer('maintenance'));
                $this->maintenanceId = $maintenance->id;
                $this->mode = 'generate';
                $this->form['type'] = 'maintenance';
                $this->form['name'] = 'Contrat de maintenance — '.$maintenance->name;
                $this->form['content'] = 'Montant : '.CommercialCalculator::euros($maintenance->amount_cents).' / '.Service::FREQUENCIES[$maintenance->frequency]."\nDébut : ".$maintenance->starts_on->format('d/m/Y')."\nDurée : ".$maintenance->duration_months." mois\nRenouvellement : ".$maintenance->renewal."\nPrestations : ".$maintenance->included."\nIntervention : ".$maintenance->response_time."\nRésiliation : ".$maintenance->termination;
            }
        }
    }

    public function create(): void
    {
        $this->revisionOf = null;
        $this->contract = [];
        $this->generatedContent = '';
        $this->reviewId = null;
        $this->reviewHash = '';
        $this->audience = '';
        $this->form = ['name' => '', 'type' => 'other', 'client_id' => '', 'client_project_id' => $this->projectFilter, 'document_date' => today()->format('Y-m-d'), 'description' => '', 'notes' => '', 'content' => '', 'template_id' => '', 'original_id' => '', 'quote_id' => '', 'invoice_id' => ''];
        $this->maintenanceId = null;
        $this->upload = null;
        $this->mode = 'upload';
        $this->showForm = true;
        $this->showAllFields = false;
        $this->visibleLegalFields = [];
        $this->autofillContext();
        $this->resetValidation();
    }

    public function sign(int $id): void
    {
        $original = Document::findOrFail($id);
        $this->create();
        $this->form['name'] = $original->name.' — signé';
        $this->form['type'] = $original->type;
        $this->form['client_id'] = $original->client_id;
        $this->form['client_project_id'] = $original->client_project_id ?? '';
        $this->form['original_id'] = $original->id;
    }

    public function updatedFormTemplateId(): void
    {
        if (empty($this->form['template_id'])) {
            return;
        }
        $this->validate(['form.template_id' => 'required|integer|exists:document_templates,id']);
        $template = DocumentTemplate::whereKey($this->form['template_id'])->firstOrFail();
        $this->form['type'] = $template->type;
        if ($template->type === 'terms') {
            $this->contract['terms_template_id'] = (string) $template->id;
        }
        $this->form['content'] = $template->content;
        if ($this->form['name'] === '') {
            $this->form['name'] = $template->name;
        }
        if ($template->type === 'terms') {
            $this->updatedContractTermsTemplateId();
        } else {
            $this->refreshLegal();
        }
    }

    private function validateFile(TemporaryUploadedFile $file): string
    {
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $allowed = ['pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'], 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip']];
        if (! isset($allowed[$extension]) || ! in_array($mime, $allowed[$extension], true)) {
            throw ValidationException::withMessages(['upload' => 'Le contenu du fichier ne correspond pas à un format autorisé : PDF, DOCX, XLSX, PNG ou JPEG.']);
        }
        if (in_array($extension, ['docx', 'xlsx'], true)) {
            if (! class_exists(ZipArchive::class)) {
                throw ValidationException::withMessages(['upload' => 'La validation des fichiers Office nécessite l’extension PHP zip.']);
            }
            $zip = new ZipArchive;
            $valid = $zip->open($file->getRealPath()) === true;
            $entry = $extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
            if ($valid) {
                $valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($entry) !== false && $zip->locateName('word/vbaProject.bin') === false && $zip->locateName('xl/vbaProject.bin') === false;
                $zip->close();
            }
            if (! $valid) {
                throw ValidationException::withMessages(['upload' => 'Le fichier Office est invalide ou contient des macros.']);
            }
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png'], true) && @getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages(['upload' => 'L’image est invalide.']);
        }

        return $extension;
    }

    public function fillDocument(): void
    {
        abort_unless($this->mode === 'generate' && in_array($this->form['type'] ?? '', ['terms', 'contract']), 422);
        try {
            $this->save(true);
        } catch (ValidationException $exception) {
            $fields = [];
            foreach ($exception->errors() as $key => $messages) {
                $fields[] = ['id' => LegalDocumentRules::errorTarget($key), 'message' => implode(' ', $messages)];
            }
            $this->visibleLegalFields = array_values(array_unique([...$this->visibleLegalFields, ...array_column($fields, 'id')]));
            $this->dispatch('document-validation-failed', fields: $fields);
            throw $exception;
        }
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'required' => 'Le champ « :attribute » est obligatoire.',
            'integer' => 'Le champ « :attribute » doit contenir un nombre entier (exemple : 1).',
            'string' => 'Le champ « :attribute » doit contenir du texte.',
            'array' => 'Les informations du formulaire sont invalides.',
            'date' => 'Le champ « :attribute » doit contenir une date valide.',
            'date_format' => 'Le champ « :attribute » doit contenir une date au format année-mois-jour.',
            'after_or_equal' => 'La date de livraison doit être postérieure ou égale à la date de début.',
            'min' => 'Le champ « :attribute » doit être supérieur ou égal à :min.',
            'max' => 'Le champ « :attribute » dépasse la limite autorisée (:max).',
            'in' => 'Choisissez une valeur proposée pour « :attribute ».',
            'exists' => 'La sélection « :attribute » est introuvable. Veuillez la sélectionner à nouveau.',
            'file' => 'Choisissez un fichier valide.',
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        $attributes = ['mode' => 'Source', 'upload' => 'Fichier'];
        foreach (['name' => 'Nom du document', 'type' => 'Type de document', 'client_id' => 'Client', 'client_project_id' => 'Projet', 'document_date' => 'Date du document', 'description' => 'Description', 'notes' => 'Notes internes', 'original_id' => 'Document original', 'template_id' => 'Modèle', 'quote_id' => 'Devis associé', 'invoice_id' => 'Facture associée', 'content' => 'Texte du document'] as $key => $label) {
            $attributes['form.'.$key] = $label;
        }
        foreach (LegalDocumentRules::formSources('contract') as $group => $definitions) {
            foreach ($definitions as $key => $label) {
                $attributes['contract.'.$group.'_'.$key] = $label;
            }
        }
        foreach (LegalDocumentRules::sections() as $definitions) {
            foreach ($definitions as $key => $label) {
                $attributes['contract.'.$key] = $label;
            }
        }
        foreach (LegalDocumentRules::CLAUSES as $key => $label) {
            $attributes['contract.clause_'.$key] = $label;
        }

        return $attributes;
    }

    public function save(bool $finalize = false): void
    {
        if ($this->mode === 'generate' && in_array($this->form['type'] ?? '', ['terms', 'contract'])) {
            $this->validate(['form.document_date' => 'required|date', 'contract.project_starts_on' => 'nullable|date_format:Y-m-d', 'contract.project_due_on' => 'nullable|date_format:Y-m-d'.(filled($this->contract['project_starts_on'] ?? null) ? '|after_or_equal:contract.project_starts_on' : ''), 'contract.terms_effective_on' => 'nullable|date_format:Y-m-d', 'contract.terms_revision' => 'nullable|integer|min:1']);
            $this->prepareLegal();
            $legal = app(ProjectLegalDocuments::class)->prepare($this->legalCandidate());
            if ($finalize && $legal['errors'] && ! TestMode::enabled()) {
                $messages = [];
                foreach ($legal['errors'] as $key => $message) {
                    $messages['contract.'.$key] = $message;
                }
                throw ValidationException::withMessages($messages);
            }
        }
        $data = $this->validate(['mode' => 'required|in:upload,generate', 'form.name' => 'required|string|max:200', 'form.type' => ['required', Rule::in(array_keys(Document::TYPES))], 'form.client_id' => 'nullable|integer|exists:clients,id', 'form.client_project_id' => 'nullable|integer|exists:client_projects,id', 'form.document_date' => 'required|date', 'form.description' => 'nullable|string|max:10000', 'form.notes' => 'nullable|string|max:20000', 'form.original_id' => 'nullable|integer|exists:documents,id', 'form.template_id' => 'nullable|integer|exists:document_templates,id', 'form.quote_id' => 'nullable|integer|exists:quotes,id', 'form.invoice_id' => 'nullable|integer|exists:invoices,id', 'form.content' => $this->mode === 'generate' ? 'required|string|max:100000' : 'nullable|string|max:100000'])['form'];
        $project = ! empty($data['client_project_id']) ? ClientProject::whereKey($data['client_project_id'])->firstOrFail() : null;
        if ($project?->archived_at) {
            throw ValidationException::withMessages(['form.client_project_id' => 'Restaurez ce dossier avant d’ajouter un document.']);
        }
        $clientId = $project ? $project->client_id : ($data['client_id'] ?: null);
        if (! $clientId || ($project && ! empty($data['client_id']) && (int) $data['client_id'] !== $project->client_id)) {
            throw ValidationException::withMessages(['form.client_id' => 'Choisissez un client cohérent avec le projet.']);
        }
        $client = Client::whereKey($clientId)->firstOrFail();
        $clientId = $client->id;
        $snapshot = $project ? app(CommercialBilling::class)->snapshot($project) : ['seller' => CommercialSetting::current(), 'client' => $client->only(['name', 'contact', 'email', 'phone', 'address', 'registration'])];
        $snapshot['audience'] = $this->audience;
        if ($this->revisionOf) {
            $previous = Document::findOrFail($this->revisionOf);
            abort_unless($this->mode === 'generate' && ! $previous->original_id && ! $previous->archived_at && $previous->client_id === $clientId && $previous->client_project_id === $project?->id && $previous->type === $data['type'], 422);
            $snapshot['previous_document_id'] = $previous->id;
            $snapshot['revision'] = ($previous->snapshot['revision'] ?? 1) + 1;
        }
        $original = ! empty($data['original_id']) ? Document::whereKey($data['original_id'])->firstOrFail() : null;
        if ($original && ($this->mode !== 'upload' || $original->client_id !== $clientId || $original->client_project_id !== $project?->id || $original->type !== $data['type'] || $original->original_id || $original->archived_at)) {
            throw ValidationException::withMessages(['form.original_id' => 'La version signée doit correspondre au document original et à son dossier.']);
        }
        if ($original) {
            $snapshot = $original->snapshot ?? $snapshot;
        }
        $quote = ! empty($data['quote_id']) ? Quote::where('client_project_id', $project?->id)->whereKey($data['quote_id'])->firstOrFail() : null;
        if (! empty($data['invoice_id'])) {
            Invoice::where('client_project_id', $project?->id)->whereKey($data['invoice_id'])->firstOrFail();
        }
        $content = $this->mode === 'generate' ? $data['content'] : null;
        if ($content !== null) {
            $snapshot['template'] = ! empty($data['template_id']) ? DocumentTemplate::whereKey($data['template_id'])->firstOrFail()->only(['id', 'name', 'revision']) : null;
            $services = $quote ? implode("\n", array_map(fn (array $item): string => $item['name'].' — '.$item['description'], array_filter($quote->items, fn (array $item): bool => ! $item['optional'] || $item['selected']))) : 'À préciser';
            $content = strtr($content, ['{{client}}' => $client->name, '{{project}}' => $project ? $project->name : '', '{{seller}}' => $snapshot['seller']['legal_name'] ?: $snapshot['seller']['name'], '{{amount}}' => $quote ? CommercialCalculator::euros($quote->totals['initial']['ttc']) : 'À préciser', '{{delay}}' => $quote ? ($quote->estimated_delay ?? 'À préciser') : 'À préciser', '{{services}}' => $services, '{{payment}}' => $quote ? ($quote->conditions ?? 'À préciser') : 'À préciser']);
        }
        if ($content !== null && in_array($data['type'], ['terms', 'contract'])) {
            $legal = app(ProjectLegalDocuments::class)->prepare($this->legalCandidate());
            $snapshot = array_replace($snapshot, $legal['snapshot']);
            $content = $legal['content'];
        }
        $path = null;
        $mime = null;
        if ($this->mode === 'upload') {
            $this->validate(['upload' => 'required|file|max:20480']);
            abort_unless($this->upload !== null, 422);
            $extension = $this->validateFile($this->upload);
            $path = $this->upload->storeAs('client-projects/'.($project ? $project->id : 'client-'.$clientId).'/uploads', (string) Str::uuid().'.'.$extension, 'local');
            if (! $path) {
                throw ValidationException::withMessages(['upload' => 'Le fichier n’a pas pu être enregistré.']);
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($this->upload->getRealPath());
        }
        try {
            $document = DB::transaction(function () use ($data, $clientId, $project, $snapshot, $content, $path, $mime, $original, $finalize): Document {
                $document = Document::create(['contract_data' => $content !== null && in_array($data['type'], ['terms', 'contract']) ? $this->contract : null, 'signed_imported_at' => $original ? now() : null, 'signed_imported_by' => $original ? auth()->id() : null, 'name' => $data['name'], 'type' => $data['type'], 'client_id' => $clientId, 'client_project_id' => $project?->id, 'document_date' => $data['document_date'], 'description' => $data['description'], 'notes' => $data['notes'], 'original_id' => $original?->id, 'quote_id' => $original ? $original->quote_id : ($data['quote_id'] ?: null), 'invoice_id' => $original ? $original->invoice_id : ($data['invoice_id'] ?: null), 'content' => $content, 'snapshot' => $snapshot, 'path' => $path, 'mime' => $mime, 'size' => $path ? $this->upload?->getSize() : null, 'original_name' => $path ? mb_substr(basename($this->upload?->getClientOriginalName() ?? ''), 0, 240) : null, 'status' => $original ? 'signed' : ($content !== null ? 'draft' : 'available')]);
                if ($path) {
                    $document->versions()->create(['version' => 1, 'path' => $path, 'mime' => $mime, 'size' => $document->size]);
                }
                if ($this->maintenanceId) {
                    $maintenance = MaintenanceContract::where('client_project_id', $project?->id)->findOrFail($this->maintenanceId);
                    $maintenance->update(['document_id' => $document->id]);
                }
                if ($project) {
                    ProjectEvent::record($project->id, ($original ? 'Version signée importée : ' : 'Document ajouté : ').$document->name);
                }
                if ($finalize && $content !== null && in_array($document->type, ['terms', 'contract'])) {
                    app(CommercialPdf::class)->document($document);
                }

                return $document;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        $this->showForm = false;
        $this->previewId = $document->id;
        if ($this->mode === 'generate' && in_array($document->type, ['terms', 'contract'])) {
            $this->reviewId = null;
            $this->reviewHash = '';
            if (! $finalize) {
                $this->review($document->id);
            }
        }
        session()->flash('success', $document->fresh()->finalized_at ? 'PDF généré. Vous pouvez le prévisualiser ou le télécharger.' : 'Document enregistré.');
    }

    public function generate(int $id): void
    {
        $document = Document::findOrFail($id);
        if (in_array($document->type, ['terms', 'contract']) && ! $document->finalized_at) {
            if ($this->reviewId !== $id) {
                $this->review($id);
                throw ValidationException::withMessages(['document' => 'Vérifiez le document et sa checklist avant de finaliser.']);
            }
        }
        app(CommercialPdf::class)->document($document, true, $this->reviewHash ?: null);
        $this->previewId = $document->id;
        session()->flash('success', 'PDF généré. Vous pouvez le prévisualiser ou le télécharger.');
    }

    public function archive(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $document = Document::findOrFail($id);
            $document->update(['archived_at' => $document->archived_at ? null : now()]);
            if ($document->client_project_id) {
                ProjectEvent::record($document->client_project_id, ($document->archived_at ? 'Document archivé : ' : 'Document restauré : ').$document->name);
            }
        });
    }

    public function preview(int $id): void
    {
        $this->previewId = Document::findOrFail($id)->id;
    }

    public function toggleCustomerVisibility(int $id): void
    {
        app(CustomerDocumentSharing::class)->toggle(Document::findOrFail($id));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProjectFilter(): void
    {
        $this->resetPage();
    }

    public function updatedClientFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedYearFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $term = '%'.mb_substr($this->search, 0, 200).'%';
        $documents = Document::with(['client', 'project', 'signedCopies'])->where(function ($q) use ($term): void {
            $q->where('name', 'like', $term)->orWhereHas('client', fn ($q) => $q->where('name', 'like', $term))->orWhereHas('project', fn ($q) => $q->where('name', 'like', $term));
        })
            ->when($this->projectFilter, fn ($q) => $q->where('client_project_id', $this->projectFilter))->when($this->clientFilter, fn ($q) => $q->where('client_id', $this->clientFilter))->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when(preg_match('/^\d{4}$/', $this->yearFilter), fn ($q) => $q->whereYear('document_date', $this->yearFilter))->when($this->dateFilter, fn ($q) => $q->whereDate('document_date', $this->dateFilter))
            ->when($this->statusFilter === 'archived', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))->when($this->statusFilter === 'signed', fn ($q) => $q->whereNotNull('original_id'))->latest()->paginate(20);

        return view('livewire.admin.commercial-documents', ['documents' => $documents, 'projects' => ClientProject::with('client')->orderBy('name')->get(), 'clients' => Client::orderBy('name')->get(), 'templates' => DocumentTemplate::orderBy('name')->get(), 'quotes' => Quote::where('status', 'accepted')->get(), 'invoices' => Invoice::whereNotNull('finalized_at')->get(), 'legalForm' => $this->showForm && $this->mode === 'generate' && in_array($this->form['type'] ?? '', ['terms', 'contract']) ? app(ProjectLegalDocuments::class)->prepare($this->legalCandidate()) : null, 'legalReview' => $this->reviewId ? app(ProjectLegalDocuments::class)->prepare(Document::findOrFail($this->reviewId)) : null, 'preview' => $this->previewId ? Document::with('versions')->findOrFail($this->previewId) : null])->layout('components.admin-layout', ['title' => 'Documents']);
    }
}
