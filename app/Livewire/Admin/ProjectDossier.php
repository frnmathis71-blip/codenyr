<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\MaintenanceContract;
use App\Models\Payment;
use App\Models\ProjectEvent;
use App\Models\ProjectNote;
use App\Models\Quote;
use App\Models\User;
use App\Services\CommercialBilling;
use App\Services\CommercialCalculator;
use App\Services\CustomerDocumentSharing;
use App\Services\InvoicePayments;
use App\Support\ProjectWebsite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;

class ProjectDossier extends AdminComponent
{
    #[Locked]
    public int $projectId;

    public string $tab = 'summary';

    public bool $editing = false;

    public string $accessClientId = '';

    public string $accountEmail = '';

    /** @var array<string, mixed> */
    public array $form = [];

    public string $note = '';

    public bool $pinned = false;

    /** @var array<string, mixed> */
    public array $payment = [];

    /** @var array<string, mixed> */
    public array $maintenanceForm = [];

    #[Locked]
    public string $paymentKey;

    public function mount(ClientProject $clientProject): void
    {
        $this->projectId = $clientProject->id;
        $this->accessClientId = (string) $clientProject->client_id;
        $this->accountEmail = $clientProject->user->email ?? '';
        $this->paymentKey = (string) Str::uuid();
        $this->payment = ['invoice_id' => '', 'amount' => '', 'paid_on' => today()->format('Y-m-d'), 'method' => 'transfer', 'reference' => '', 'comment' => ''];
        $this->maintenanceForm = ['name' => 'Maintenance du site', 'amount' => '', 'frequency' => 'monthly', 'starts_on' => today()->format('Y-m-d'), 'duration_months' => 12, 'renewal' => '', 'included' => '', 'response_time' => '', 'termination' => '', 'notes' => ''];
    }

    private function project(): ClientProject
    {
        return ClientProject::findOrFail($this->projectId);
    }

    public function updatedAccessClientId(): void
    {
        $this->accountEmail = '';
    }

    public function saveAccess(): void
    {
        $this->accountEmail = trim($this->accountEmail);
        $this->validate(['accessClientId' => 'required|integer|exists:clients,id', 'accountEmail' => 'nullable|email|max:255'], [
            'accessClientId.required' => 'Choisissez le client du projet.',
            'accessClientId.exists' => 'Ce client n’existe plus.',
            'accountEmail.email' => 'Saisissez l’adresse e-mail du compte client.',
        ]);
        $user = $this->accountEmail === '' ? null : User::whereRaw('LOWER(email) = ?', [mb_strtolower($this->accountEmail)])->where('is_admin', false)->first();
        if ($this->accountEmail !== '' && ! $user) {
            throw ValidationException::withMessages(['accountEmail' => 'Aucun compte client ne correspond. Le client doit d’abord créer son compte sur le site.']);
        }
        DB::transaction(function () use ($user): void {
            $project = ClientProject::lockForUpdate()->findOrFail($this->projectId);
            $clientChanged = $project->client_id !== (int) $this->accessClientId;
            $accountChanged = $project->user_id !== $user?->id;
            if (! $clientChanged && ! $accountChanged) {
                return;
            }
            $previous = $project->client->name;
            $project->update(['client_id' => (int) $this->accessClientId, 'user_id' => $user?->id]);
            $project->documents()->update(['client_visible' => false]);
            ProjectEvent::record($project->id, 'Client / accès mis à jour : '.$previous.' → '.Client::findOrFail($this->accessClientId)->name.' ; compte : '.($user->email ?? 'aucun').'. Partage des documents retiré.');
        });
        session()->flash('success', 'Client et accès enregistrés. Partagez les documents souhaités depuis le dossier.');
    }

    public function toggleCustomerVisibility(int $id): void
    {
        app(CustomerDocumentSharing::class)->toggle(Document::where('client_project_id', $this->projectId)->findOrFail($id));
    }

    private function writable(): ClientProject
    {
        $project = $this->project();
        if ($project->archived_at) {
            throw ValidationException::withMessages(['project' => 'Restaurez le dossier avant de le modifier.']);
        }

        return $project;
    }

    public function edit(): void
    {
        $project = $this->writable();
        $this->form = $project->only(['name', 'type', 'status', 'description', 'domain', 'host', 'website_url', 'internal_notes']);
        $this->form['starts_on'] = $project->starts_on?->format('Y-m-d') ?? '';
        $this->form['due_on'] = $project->due_on?->format('Y-m-d') ?? '';
        $this->editing = true;
    }

    public function save(): void
    {
        if (is_string($this->form['website_url'] ?? null)) {
            $this->form['website_url'] = ProjectWebsite::normalize($this->form['website_url']);
        }
        foreach (['starts_on', 'due_on'] as $field) {
            $this->form[$field] = $this->form[$field] ?: null;
        }
        $data = $this->validate(['form.name' => 'required|string|max:200', 'form.type' => ['required', Rule::in(ClientProject::TYPES)], 'form.status' => ['required', Rule::in(array_keys(ClientProject::STATUSES))], 'form.description' => 'nullable|string|max:20000', 'form.internal_notes' => 'nullable|string|max:20000', 'form.starts_on' => 'nullable|date', 'form.due_on' => 'nullable|date|after_or_equal:form.starts_on', 'form.domain' => 'nullable|string|max:255', 'form.host' => 'nullable|string|max:255', 'form.website_url' => 'nullable|string|url:http,https|max:255'], [
            'form.website_url.url' => 'Saisissez une adresse de site valide (exemple : www.exemple.fr) ou laissez ce champ vide.',
            'form.website_url.string' => 'L’adresse du site doit être du texte.',
            'form.website_url.max' => 'L’adresse du site ne doit pas dépasser 255 caractères.',
        ])['form'];
        DB::transaction(function () use ($data): void {
            $project = $this->writable();
            $project->update($data);
            ProjectEvent::record($project->id, 'Projet mis à jour — '.ClientProject::STATUSES[$project->status]);
        });
        $this->editing = false;
        session()->flash('success', 'Projet enregistré.');
    }

    public function archive(): void
    {
        DB::transaction(function (): void {
            $project = $this->project();
            $archived = ! $project->archived_at;
            $project->update(['archived_at' => $archived ? now() : null]);
            ProjectEvent::record($project->id, $archived ? 'Projet archivé' : 'Projet restauré');
        });
    }

    public function addNote(): void
    {
        $this->writable();
        $data = $this->validate(['note' => 'required|string|max:20000', 'pinned' => 'boolean']);
        DB::transaction(function () use ($data): void {
            ProjectNote::create(['client_project_id' => $this->projectId, 'user_id' => auth()->id(), 'content' => $data['note'], 'pinned' => $data['pinned']]);
            ProjectEvent::record($this->projectId, 'Note ajoutée');
        });
        $this->note = '';
        $this->pinned = false;
        session()->flash('success', 'Note ajoutée.');
    }

    public function pinNote(int $id): void
    {
        $this->writable();
        $note = ProjectNote::where('client_project_id', $this->projectId)->findOrFail($id);
        $note->update(['pinned' => ! $note->pinned]);
    }

    public function pay(): void
    {
        $this->writable();
        $data = $this->validate(['payment.invoice_id' => 'required|integer', 'payment.amount' => 'required|string', 'payment.paid_on' => 'required|date|before_or_equal:today', 'payment.method' => ['required', Rule::in(array_keys(Payment::METHODS))], 'payment.reference' => 'nullable|string|max:255', 'payment.comment' => 'nullable|string|max:5000'])['payment'];
        $invoice = Invoice::where('client_project_id', $this->projectId)->whereKey($data['invoice_id'])->firstOrFail();
        app(InvoicePayments::class)->record($invoice->id, $data, $this->paymentKey);
        $this->paymentKey = (string) Str::uuid();
        $this->payment['amount'] = '';
        session()->flash('success', 'Paiement enregistré.');
    }

    public function addMaintenance(): void
    {
        $this->writable();
        $data = $this->validate(['maintenanceForm.name' => 'required|string|max:200', 'maintenanceForm.amount' => 'required|string', 'maintenanceForm.frequency' => 'required|in:monthly,quarterly,yearly', 'maintenanceForm.starts_on' => 'required|date', 'maintenanceForm.duration_months' => 'required|integer|between:1,120', 'maintenanceForm.renewal' => 'nullable|string|max:255', 'maintenanceForm.included' => 'nullable|string|max:20000', 'maintenanceForm.response_time' => 'nullable|string|max:255', 'maintenanceForm.termination' => 'nullable|string|max:10000', 'maintenanceForm.notes' => 'nullable|string|max:10000'])['maintenanceForm'];
        $cents = CommercialCalculator::decimal($data['amount']);
        unset($data['amount']);
        DB::transaction(function () use ($data, $cents): void {
            MaintenanceContract::create([...$data, 'client_project_id' => $this->projectId, 'amount_cents' => $cents]);
            ProjectEvent::record($this->projectId, 'Maintenance préparée : '.$data['name']);
        });
        session()->flash('success', 'Maintenance ajoutée. Générez son contrat ci-dessous.');
    }

    public function maintenanceStatus(int $id, string $status): void
    {
        $this->writable();
        $this->validate(['tab' => 'string']);
        abort_unless(array_key_exists($status, MaintenanceContract::STATUSES), 422);
        DB::transaction(function () use ($id, $status): void {
            $contract = MaintenanceContract::where('client_project_id', $this->projectId)->findOrFail($id);
            $contract->update(['status' => $status]);
            ProjectEvent::record($this->projectId, 'Maintenance : '.MaintenanceContract::STATUSES[$status]);
        });
    }

    public function invoiceFromQuote(int $quoteId, string $type): void
    {
        $this->writable();
        $quote = Quote::where('client_project_id', $this->projectId)->findOrFail($quoteId);
        $invoice = app(CommercialBilling::class)->fromQuote($quote, $type);
        $this->redirectRoute('admin.billing.edit', ['kind' => 'invoices', 'id' => $invoice->id]);
    }

    public function render(): View
    {
        abort_unless(in_array($this->tab, ['summary', 'quotes', 'invoices', 'payments', 'documents', 'contracts', 'maintenance', 'files', 'notes', 'history'], true), 404);
        $project = $this->project()->load(['client', 'user', 'quotes', 'invoices.payments', 'documents.signedCopies', 'maintenance', 'notes.author', 'events']);
        $issued = $project->invoices->whereNotNull('finalized_at');
        $billed = (int) $issued->sum(fn (Invoice $invoice): int => $invoice->totals['initial']['ttc']);
        $paid = (int) $issued->sum(fn (Invoice $invoice): int => $invoice->paidCents());

        return view('livewire.admin.project-dossier', ['project' => $project, 'clients' => Client::orderBy('name')->get(), 'billed' => $billed, 'paid' => $paid, 'checklist' => $project->checklist(), 'alerts' => $project->archived_at ? [] : $project->alerts()])->layout('components.admin-layout', ['title' => $project->name]);
    }
}
