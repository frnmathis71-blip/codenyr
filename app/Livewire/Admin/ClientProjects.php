<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Lead;
use App\Models\ProjectEvent;
use App\Support\ProjectWebsite;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

class ClientProjects extends AdminComponent
{
    use WithPagination;

    public string $search = '';

    public string $filter = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $sourceLead = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<string, mixed> */
    public array $clientForm = [];

    public function mount(): void
    {
        $this->search = mb_substr((string) request()->query('search', ''), 0, 200);
        if (request()->filled('lead')) {
            $lead = Lead::findOrFail(request()->integer('lead'));
            $this->create();
            $this->sourceLead = $lead->id;
            $this->form['name'] = $lead->project_type.' — '.($lead->company ?: $lead->firstname.' '.$lead->lastname);
            $this->form['description'] = $lead->description;
            $this->form['website_url'] = $lead->website ?? '';
            $this->clientForm = ['name' => $lead->company ?: $lead->firstname.' '.$lead->lastname, 'contact' => $lead->firstname.' '.$lead->lastname, 'email' => $lead->email, 'phone' => $lead->phone ?? '', 'address' => ''];
        }
    }

    public function create(): void
    {
        $this->form = ['client_id' => '', 'name' => '', 'type' => 'Site vitrine', 'description' => '', 'status' => 'quote_to_prepare', 'starts_on' => '', 'due_on' => '', 'website_url' => '', 'domain' => '', 'host' => '', 'internal_notes' => ''];
        $this->clientForm = ['name' => '', 'contact' => '', 'email' => '', 'phone' => '', 'address' => ''];
        $this->sourceLead = null;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        if (is_string($this->form['website_url'] ?? null)) {
            $this->form['website_url'] = ProjectWebsite::normalize($this->form['website_url']);
        }
        foreach (['starts_on', 'due_on'] as $field) {
            $this->form[$field] = $this->form[$field] ?: null;
        }
        $data = $this->validate([
            'form.client_id' => 'nullable|integer|exists:clients,id', 'form.name' => 'required|string|max:200',
            'form.type' => ['required', Rule::in(ClientProject::TYPES)], 'form.status' => ['required', Rule::in(array_keys(ClientProject::STATUSES))],
            'form.description' => 'nullable|string|max:20000', 'form.starts_on' => 'nullable|date', 'form.due_on' => 'nullable|date|after_or_equal:form.starts_on',
            'form.website_url' => 'nullable|string|url:http,https|max:255', 'form.domain' => 'nullable|string|max:255', 'form.host' => 'nullable|string|max:255', 'form.internal_notes' => 'nullable|string|max:20000',
        ], [
            'form.website_url.url' => 'Saisissez une adresse de site valide (exemple : www.exemple.fr) ou laissez ce champ vide.',
            'form.website_url.string' => 'L’adresse du site doit être du texte.',
            'form.website_url.max' => 'L’adresse du site ne doit pas dépasser 255 caractères.',
        ])['form'];
        $clientData = [];
        if (empty($data['client_id'])) {
            $clientData = $this->validate(['clientForm.name' => 'required|string|max:200', 'clientForm.contact' => 'nullable|string|max:200', 'clientForm.email' => 'required|email|max:255', 'clientForm.phone' => 'nullable|string|max:50', 'clientForm.address' => 'nullable|string|max:2000'])['clientForm'];
        }
        $project = DB::transaction(function () use ($data, $clientData): ClientProject {
            $clientId = $data['client_id'] ?: Client::create($clientData)->id;
            $project = ClientProject::create([...$data, 'client_id' => $clientId, 'lead_id' => $this->sourceLead]);
            ProjectEvent::record($project->id, 'Projet créé'.($this->sourceLead ? ' depuis le prospect #'.$this->sourceLead : ''));

            return $project;
        });
        $this->redirectRoute('admin.client-projects.show', ['clientProject' => $project->id]);
    }

    public function render(): View
    {
        $projects = ClientProject::with('client')->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
            $term = '%'.mb_substr($this->search, 0, 200).'%';
            $q->where('name', 'like', $term)->orWhereHas('client', fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }))->when($this->filter === 'archived', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when($this->filter === 'active', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->when($this->filter === 'completed', fn ($q) => $q->where('status', 'completed'))->latest()->paginate(15);

        return view('livewire.admin.client-projects', ['projects' => $projects, 'clients' => Client::orderBy('name')->get()])->layout('components.admin-layout', ['title' => 'Projets']);
    }
}
