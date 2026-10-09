<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\CommercialSetting;
use App\Models\DocumentTemplate;
use App\Models\Price;
use App\Models\Service;
use App\Services\CommercialCalculator;
use App\Services\CommercialCatalog;
use App\Services\CommercialConfiguration;
use App\Services\ContractCatalogRules;
use App\Services\LegalDocumentRules;
use App\Services\PricingCatalog;
use App\Services\TermsVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class CommercialDirectory extends AdminComponent
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $logo = null;

    #[Locked]
    public string $module = 'clients';

    #[Locked]
    public ?int $editing = null;

    public bool $showForm = false;

    public string $search = '';

    public string $categoryFilter = '';

    public string $stateFilter = '';

    public string $frequencyFilter = '';

    public string $offerFilter = '';

    public string $pricingFilter = '';

    public string $catalogGroup = '';

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<string, mixed> */
    public array $contractRules = [];

    public function mount(string $module = 'clients'): void
    {
        abort_unless(in_array($module, ['clients', 'catalog', 'settings', 'templates'], true), 404);
        $this->module = $module;
        $this->catalogGroup = in_array(request()->query('group'), ['offers', 'options'], true) ? request()->query('group') : '';
        if ($module === 'catalog') {
            $this->initializeCatalog();
        }
        if ($module === 'settings') {
            $this->form = CommercialSetting::current();
            $this->showForm = true;
        }
    }

    private function initializeCatalog(): void
    {
        app(CommercialCatalog::class)->initialize();
    }

    public function create(): void
    {
        $this->editing = null;
        $this->contractRules = ContractCatalogRules::editor([]);
        $this->form = match ($this->module) {
            'clients' => ['name' => '', 'contact' => '', 'email' => '', 'phone' => '', 'address' => '', 'registration' => '', 'audience' => '', 'company' => '', 'country' => '', 'vat_number' => '', 'signatory' => '', 'signatory_role' => ''],
            'catalog' => ['category' => 'Fonctionnalités', 'name' => '', 'description' => '', 'price' => '0.00', 'unit' => 'forfait', 'frequency' => 'once', 'active' => true, 'position' => 0, 'pricing_mode' => 'fixed', 'compatible_offers' => []],
            'templates' => ['name' => '', 'type' => 'terms', 'content' => '', 'effective_on' => today()->format('Y-m-d')],
            default => CommercialSetting::current(),
        };
        $this->showForm = true;
        if ($this->module === 'clients') {
            $this->form += array_fill_keys(array_keys(CommercialConfiguration::clientFields()), '');
        }
        if ($this->module === 'templates') {
            $this->form += ['version_label' => '', 'status' => 'draft'];
        }
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $this->create();
        $record = match ($this->module) {
            'clients' => Client::findOrFail($id), 'catalog' => Service::findOrFail($id), 'templates' => DocumentTemplate::findOrFail($id), default => abort(404)
        };
        $this->editing = $record->id;
        $this->form = $record->only(array_keys($this->form));
        if ($record instanceof Service) {
            $this->contractRules = ContractCatalogRules::editor($record->contract_rules ?? []);
            $this->form['price'] = CommercialCalculator::input($record->currentPrice());
            $this->form['compatible_offers'] = $record->compatible_offers ?? [];
        }
    }

    public function save(): void
    {
        $rules = match ($this->module) {
            'clients' => ['name' => 'required|string|max:200', 'contact' => 'nullable|string|max:200', 'email' => 'required|email|max:255', 'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:2000', 'registration' => 'nullable|string|max:100'],
            'catalog' => ['category' => 'required|string|max:100', 'name' => 'required|string|max:200', 'description' => 'nullable|string|max:5000', 'price' => 'required|string', 'unit' => 'required|string|max:50', 'frequency' => ['required', Rule::in(array_keys(Service::FREQUENCIES))], 'active' => 'boolean', 'position' => 'integer|between:0,10000', 'pricing_mode' => ['required', Rule::in(array_keys(Service::PRICING_MODES))], 'compatible_offers' => 'array', 'compatible_offers.*' => ['string', Rule::in(['offer_landing', 'offer_vitrine', 'offer_pro', 'offer_custom'])]],
            'templates' => ['name' => 'required|string|max:200', 'type' => 'required|in:terms,contract,maintenance,legal,commercial,other', 'content' => 'required|string|max:100000'],
            default => ['name' => 'required|string|max:200', 'legal_name' => 'nullable|string|max:200', 'address' => 'nullable|string|max:2000', 'phone' => 'nullable|string|max:50', 'email' => 'required|email|max:255', 'registration' => 'nullable|string|max:100', 'vat_enabled' => 'boolean', 'vat_rate' => 'required|numeric|between:0,100|decimal:0,2', 'tax_mention' => 'nullable|string|max:2000', 'quote_prefix' => 'required|alpha_dash:ascii|max:20', 'invoice_prefix' => 'required|alpha_dash:ascii|max:20', 'validity_days' => 'required|integer|between:1,365', 'payment_days' => 'required|integer|between:0,365', 'deposit_percent' => 'required|numeric|between:0,100|decimal:0,2', 'quote_followup_days' => 'required|integer|between:1,365', 'delivery_alert_days' => 'required|integer|between:1,60', 'external_fees_mention' => 'nullable|string|max:20000', 'quote_mention' => 'nullable|string|max:20000', 'invoice_mention' => 'nullable|string|max:20000', 'footer' => 'nullable|string|max:2000'],
        };
        if ($this->module === 'clients') {
            foreach (CommercialConfiguration::clientFields() as $key => $label) {
                $rules[$key] = 'nullable|string|max:2000';
            }
            $rules['audience'] = 'nullable|in:professional,consumer';
            foreach (['company', 'country', 'vat_number', 'signatory', 'signatory_role'] as $key) {
                $rules[$key] = 'nullable|string|max:255';
            }
        }
        if ($this->module === 'templates') {
            $rules['effective_on'] = 'nullable|date';
            $rules['version_label'] = 'nullable|string|max:50';
            $rules['status'] = 'required|in:draft,active,archived';
        }
        if ($this->module === 'settings') {
            foreach (array_keys(LegalDocumentRules::defaults()) as $key) {
                $rules[$key] = 'nullable|string|max:20000';
            }
            foreach (CommercialConfiguration::sections() as $fields) {
                foreach ($fields as $key => $label) {
                    $rules[$key] = 'nullable|string|max:20000';
                }
            }
            $rules['currency'] = 'required|in:EUR';
            foreach (['require_contract_quote', 'require_contract_terms'] as $key) {
                $rules[$key] = 'boolean';
            }
            foreach (['terms_prefix', 'contract_prefix', 'maintenance_prefix', 'document_prefix'] as $key) {
                $rules[$key] = 'required|alpha_dash:ascii|max:20';
            }
            $rules['extra_hourly_rate'] = 'required|numeric|min:0';
        }
        $data = $this->validate(collect($rules)->mapWithKeys(fn ($rule, $key) => ['form.'.$key => $rule])->all())['form'];
        if ($this->module === 'catalog') {
            $data['contract_rules'] = ContractCatalogRules::fromEditor($this->contractRules, $this->editing ? (Service::findOrFail($this->editing)->contract_rules ?? []) : []);
        }
        $logoPath = null;
        if ($this->module === 'settings' && $this->logo) {
            $this->validate(['logo' => 'image|mimes:png,jpg,jpeg|max:2048']);
            $logoPath = $this->logo->storeAs('commercial/logos', Str::uuid().'.'.$this->logo->guessExtension(), 'local');
            abort_unless(is_string($logoPath), 500);
        }
        try {
            DB::transaction(function () use ($data, $logoPath): void {
                if ($this->module === 'settings') {
                    CommercialSetting::updateOrCreate(['id' => 1], ['values' => [...$data, 'logo_path' => $logoPath ?? CommercialSetting::current()['logo_path']]]);

                    return;
                }
                if ($this->module === 'clients') {
                    Client::updateOrCreate(['id' => $this->editing], $data);

                    return;
                }
                if ($this->module === 'templates') {
                    $template = $this->editing ? DocumentTemplate::lockForUpdate()->findOrFail($this->editing) : new DocumentTemplate;
                    $next = DocumentTemplate::create([...$data, 'status' => 'draft', 'previous_id' => $template->id, 'revision' => $template->exists ? $template->revision + 1 : 1]);
                    if (($data['status'] ?? 'draft') !== 'draft') {
                        app(TermsVersions::class)->transition($next, $data['status']);
                    }

                    return;
                }
                $cents = $data['pricing_mode'] === 'quote' ? 0 : CommercialCalculator::decimal($data['price']);
                if (in_array($data['pricing_mode'], ['monthly', 'yearly'], true)) {
                    $data['frequency'] = $data['pricing_mode'];
                }
                if ($data['pricing_mode'] === 'hourly') {
                    $data['unit'] = 'heure';
                }
                unset($data['price']);
                $service = Service::updateOrCreate(['id' => $this->editing], [...$data, 'amount_cents' => $cents]);
                if ($service->price_key) {
                    Price::updateOrCreate(['key' => $service->price_key], ['amount_cents' => $cents]);
                }
            });
        } catch (\Throwable $exception) {
            if ($logoPath) {
                Storage::disk('local')->delete($logoPath);
            }
            throw $exception;
        }
        $this->logo = null;
        $this->showForm = $this->module === 'settings';
        session()->flash('success', 'Modifications enregistrées.');
    }

    public function templateStatus(int $id, string $status): void
    {
        abort_unless($this->module === 'templates', 404);
        app(TermsVersions::class)->transition(DocumentTemplate::findOrFail($id), $status);
        session()->flash('success', 'Statut de la version mis à jour.');
    }

    public function archive(int $id): void
    {
        abort_unless($this->module === 'catalog', 404);
        $service = Service::findOrFail($id);
        $service->update(['archived_at' => $service->archived_at ? null : now()]);
    }

    public function render(): View
    {
        $term = '%'.mb_substr($this->search, 0, 200).'%';
        $records = match ($this->module) {
            'clients' => Client::where('name', 'like', $term)->orWhere('email', 'like', $term)->orderBy('name')->get(),
            'catalog' => Service::where(fn ($q) => $q->where('name', 'like', $term)->orWhere('description', 'like', $term))
                ->when($this->catalogGroup === 'offers', fn ($q) => $q->where('price_key', 'like', 'offer_%'))
                ->when($this->catalogGroup === 'options', fn ($q) => $q->where(fn ($q) => $q->whereNull('price_key')->orWhere('price_key', 'not like', 'offer_%')))
                ->when($this->categoryFilter !== '', fn ($q) => $q->where('category', $this->categoryFilter))
                ->when($this->stateFilter === 'archived', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
                ->when(in_array($this->stateFilter, ['active', 'inactive']), fn ($q) => $q->where('active', $this->stateFilter === 'active'))
                ->when($this->frequencyFilter === 'once', fn ($q) => $q->where('frequency', 'once'))
                ->when($this->frequencyFilter === 'recurring', fn ($q) => $q->where('frequency', '!=', 'once'))
                ->when($this->offerFilter !== '', fn ($q) => $q->where(fn ($q) => $q->whereNull('compatible_offers')->orWhereJsonLength('compatible_offers', 0)->orWhereJsonContains('compatible_offers', $this->offerFilter)))
                ->when($this->pricingFilter !== '', fn ($q) => $q->where('pricing_mode', $this->pricingFilter))->orderBy('category')->orderBy('position')->get(),
            'templates' => DocumentTemplate::where('name', 'like', $term)->orderBy('name')->get(),
            default => collect(),
        };
        $title = ['clients' => 'Clients', 'catalog' => 'Prestations et options', 'templates' => 'Modèles de documents', 'settings' => 'Paramètres commerciaux'][$this->module];

        return view('livewire.admin.commercial-directory', ['records' => $records, 'title' => $title, 'offers' => app(PricingCatalog::class)->offers(), 'categories' => Service::distinct()->orderBy('category')->pluck('category')])->layout('components.admin-layout', ['title' => $title]);
    }
}
