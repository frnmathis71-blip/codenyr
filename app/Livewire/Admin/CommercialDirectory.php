<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\CommercialSetting;
use App\Models\DocumentTemplate;
use App\Models\Price;
use App\Models\Service;
use App\Services\CommercialCalculator;
use App\Services\CommercialCatalog;
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

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(string $module = 'clients'): void
    {
        abort_unless(in_array($module, ['clients', 'catalog', 'settings', 'templates'], true), 404);
        $this->module = $module;
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
        $this->form = match ($this->module) {
            'clients' => ['name' => '', 'contact' => '', 'email' => '', 'phone' => '', 'address' => '', 'registration' => ''],
            'catalog' => ['category' => 'Fonctionnalités', 'name' => '', 'description' => '', 'price' => '0.00', 'unit' => 'forfait', 'frequency' => 'once', 'active' => true, 'position' => 0],
            'templates' => ['name' => '', 'type' => 'terms', 'content' => ''],
            default => CommercialSetting::current(),
        };
        $this->showForm = true;
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
            $this->form['price'] = CommercialCalculator::input($record->currentPrice());
        }
    }

    public function save(): void
    {
        $rules = match ($this->module) {
            'clients' => ['name' => 'required|string|max:200', 'contact' => 'nullable|string|max:200', 'email' => 'required|email|max:255', 'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:2000', 'registration' => 'nullable|string|max:100'],
            'catalog' => ['category' => 'required|string|max:100', 'name' => 'required|string|max:200', 'description' => 'nullable|string|max:5000', 'price' => 'required|string', 'unit' => 'required|string|max:50', 'frequency' => ['required', Rule::in(array_keys(Service::FREQUENCIES))], 'active' => 'boolean', 'position' => 'integer|between:0,10000'],
            'templates' => ['name' => 'required|string|max:200', 'type' => 'required|in:terms,contract,maintenance,legal,commercial,other', 'content' => 'required|string|max:100000'],
            default => ['name' => 'required|string|max:200', 'legal_name' => 'nullable|string|max:200', 'address' => 'nullable|string|max:2000', 'phone' => 'nullable|string|max:50', 'email' => 'required|email|max:255', 'registration' => 'nullable|string|max:100', 'vat_enabled' => 'boolean', 'vat_rate' => 'required|numeric|between:0,100|decimal:0,2', 'tax_mention' => 'nullable|string|max:2000', 'quote_prefix' => 'required|alpha_dash:ascii|max:20', 'invoice_prefix' => 'required|alpha_dash:ascii|max:20', 'validity_days' => 'required|integer|between:1,365', 'payment_days' => 'required|integer|between:0,365', 'deposit_percent' => 'required|numeric|between:0,100|decimal:0,2', 'quote_followup_days' => 'required|integer|between:1,365', 'delivery_alert_days' => 'required|integer|between:1,60', 'quote_mention' => 'nullable|string|max:20000', 'invoice_mention' => 'nullable|string|max:20000', 'footer' => 'nullable|string|max:2000'],
        };
        $data = $this->validate(collect($rules)->mapWithKeys(fn ($rule, $key) => ['form.'.$key => $rule])->all())['form'];
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
                    $template->fill([...$data, 'revision' => $template->exists ? $template->revision + 1 : 1])->save();

                    return;
                }
                $cents = CommercialCalculator::decimal($data['price']);
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

    public function render(): View
    {
        $term = '%'.mb_substr($this->search, 0, 200).'%';
        $records = match ($this->module) {
            'clients' => Client::where('name', 'like', $term)->orWhere('email', 'like', $term)->orderBy('name')->get(),
            'catalog' => Service::where('name', 'like', $term)->orderBy('category')->orderBy('position')->get(),
            'templates' => DocumentTemplate::where('name', 'like', $term)->orderBy('name')->get(),
            default => collect(),
        };
        $title = ['clients' => 'Clients', 'catalog' => 'Catalogue de prestations', 'templates' => 'Modèles de documents', 'settings' => 'Paramètres commerciaux'][$this->module];

        return view('livewire.admin.commercial-directory', ['records' => $records, 'title' => $title])->layout('components.admin-layout', ['title' => $title]);
    }
}
