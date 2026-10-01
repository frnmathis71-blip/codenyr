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

    public function mount(): void
    {
        $this->projectFilter = (string) (request()->integer('project') ?: '');
        if (request()->boolean('create')) {
            $this->create();
            $type = request()->query('type');
            if (is_string($type) && array_key_exists($type, Document::TYPES)) {
                $this->form['type'] = $type;
            }
            if (request()->query('mode') === 'generate') {
                $this->mode = 'generate';
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
        $this->form = ['name' => '', 'type' => 'other', 'client_id' => '', 'client_project_id' => $this->projectFilter, 'document_date' => today()->format('Y-m-d'), 'description' => '', 'notes' => '', 'content' => '', 'template_id' => '', 'original_id' => '', 'quote_id' => '', 'invoice_id' => ''];
        $this->maintenanceId = null;
        $this->upload = null;
        $this->mode = 'upload';
        $this->showForm = true;
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
        $this->form['content'] = $template->content;
        if ($this->form['name'] === '') {
            $this->form['name'] = $template->name;
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

    public function save(): void
    {
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
            $document = DB::transaction(function () use ($data, $clientId, $project, $snapshot, $content, $path, $mime, $original): Document {
                $document = Document::create(['name' => $data['name'], 'type' => $data['type'], 'client_id' => $clientId, 'client_project_id' => $project?->id, 'document_date' => $data['document_date'], 'description' => $data['description'], 'notes' => $data['notes'], 'original_id' => $original?->id, 'quote_id' => $original ? $original->quote_id : ($data['quote_id'] ?: null), 'invoice_id' => $original ? $original->invoice_id : ($data['invoice_id'] ?: null), 'content' => $content, 'snapshot' => $snapshot, 'path' => $path, 'mime' => $mime, 'size' => $path ? $this->upload?->getSize() : null, 'original_name' => $path ? mb_substr(basename($this->upload?->getClientOriginalName() ?? ''), 0, 240) : null, 'status' => $original ? 'signed' : 'available']);
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
        session()->flash('success', 'Document enregistré.');
    }

    public function generate(int $id): void
    {
        $document = Document::findOrFail($id);
        app(CommercialPdf::class)->document($document);
        $this->redirectRoute('admin.documents.download', ['document' => $document->id]);
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

        return view('livewire.admin.commercial-documents', ['documents' => $documents, 'projects' => ClientProject::with('client')->orderBy('name')->get(), 'clients' => Client::orderBy('name')->get(), 'templates' => DocumentTemplate::orderBy('name')->get(), 'quotes' => Quote::where('status', 'accepted')->get(), 'invoices' => Invoice::whereNotNull('finalized_at')->get(), 'preview' => $this->previewId ? Document::with('versions')->findOrFail($this->previewId) : null])->layout('components.admin-layout', ['title' => 'Documents']);
    }
}
