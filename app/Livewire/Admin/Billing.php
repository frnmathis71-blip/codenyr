<?php

namespace App\Livewire\Admin;

use App\Models\BillingRecord;
use App\Models\ClientProject;
use App\Models\CommercialSetting;
use App\Models\Invoice;
use App\Models\ProjectEvent;
use App\Models\Quote;
use App\Models\Service;
use App\Services\CommercialBilling;
use App\Services\CommercialCalculator;
use App\Services\CommercialCatalog;
use App\Services\CommercialPdf;
use App\Services\InvoicePayments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

class Billing extends AdminComponent
{
    use WithPagination;

    #[Locked]
    public string $kind = 'quotes';

    #[Locked]
    public ?int $recordId = null;

    #[Locked]
    public bool $editor = false;

    public string $search = '';

    public string $filter = '';

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    /** @var array<int, int> */
    public array $selectedServices = [];

    public string $statusChoice = 'ready';

    public string $invoiceType = 'deposit';

    public string $interimAmount = '';

    /** @var array<string, mixed> */
    public array $payment = [];

    #[Locked]
    public string $paymentKey;

    public string $correctionReason = '';

    public function mount(string $kind, ?int $id = null): void
    {
        $this->paymentKey = (string) Str::uuid();
        $this->payment = ['amount' => '', 'paid_on' => today()->format('Y-m-d'), 'method' => 'transfer', 'reference' => '', 'comment' => ''];
        abort_unless(in_array($kind, ['quotes', 'invoices'], true), 404);
        app(CommercialCatalog::class)->initialize();
        $this->kind = $kind;
        $this->recordId = $id;
        $this->editor = $id !== null || request()->routeIs('admin.billing.*.create');
        if ($id) {
            $this->loadRecord();
        } elseif ($this->editor) {
            $settings = CommercialSetting::current();
            $this->form = ['client_project_id' => request()->integer('project') ?: '', 'issued_on' => today()->format('Y-m-d'), 'due_on' => today()->addDays((int) $settings[$kind === 'quotes' ? 'validity_days' : 'payment_days'])->format('Y-m-d'), 'discount_type' => 'percent', 'discount' => '0', 'deposit_type' => $kind === 'quotes' ? 'percent' : 'none', 'deposit' => (string) $settings['deposit_percent'], 'conditions' => $settings[$kind === 'quotes' ? 'quote_mention' : 'invoice_mention'], 'estimated_delay' => '', 'invoice_type' => 'standard'];
            $this->addItem();
        }
    }

    private function record(): BillingRecord
    {
        return $this->kind === 'quotes' ? Quote::findOrFail($this->recordId) : Invoice::findOrFail($this->recordId);
    }

    public function pay(): void
    {
        abort_unless($this->kind === 'invoices' && $this->recordId, 404);
        app(InvoicePayments::class)->record($this->recordId, $this->payment, $this->paymentKey);
        $this->paymentKey = (string) Str::uuid();
        $this->payment['amount'] = '';
        session()->flash('success', 'Règlement enregistré. L’état de la facture est mis à jour.');
    }

    public function fillBalance(): void
    {
        abort_unless($this->kind === 'invoices' && $this->recordId, 404);
        $this->payment['amount'] = CommercialCalculator::input(Invoice::findOrFail($this->recordId)->remainingCents());
    }

    public function voidPayment(int $id): void
    {
        abort_unless($this->kind === 'invoices' && $this->recordId, 404);
        app(InvoicePayments::class)->void($this->recordId, $id, $this->correctionReason);
        $this->correctionReason = '';
        session()->flash('success', 'Saisie du règlement annulée ; historique conservé et état recalculé.');
    }

    private function loadRecord(): void
    {
        $record = $this->record();
        $this->form = $record->only(['client_project_id', 'discount_type', 'deposit_type', 'conditions', 'estimated_delay']);
        $this->form['issued_on'] = $record->issued_on->format('Y-m-d');
        $this->form['due_on'] = $record->due_on->format('Y-m-d');
        $this->form['discount'] = CommercialCalculator::input($record->discount_value);
        $this->form['deposit'] = CommercialCalculator::input($record->deposit_value);
        $this->form['invoice_type'] = $record instanceof Invoice ? $record->invoice_type : 'standard';
        $this->statusChoice = $record->finalized_at ? 'accepted' : 'ready';
        $this->items = array_map(fn (array $item): array => ['service_id' => $item['service_id'] ?? null, 'name' => $item['name'], 'description' => $item['description'], 'unit' => $item['unit'], 'quantity' => CommercialCalculator::input($item['quantity_units']), 'price' => CommercialCalculator::input($item['price_cents']), 'discount_type' => $item['discount_type'], 'discount' => CommercialCalculator::input($item['discount_value']), 'vat' => CommercialCalculator::input($item['vat_basis']), 'frequency' => $item['source_frequency'] ?? $item['frequency'], 'period_start' => $item['period_start'] ?? '', 'period_end' => $item['period_end'] ?? '', 'optional' => $item['optional'], 'selected' => $item['selected']], $record->items);
        $this->selectedServices = array_values(array_unique(array_filter(array_column($this->items, 'service_id'))));
    }

    private function ensureEditable(): void
    {
        if ($this->recordId) {
            $record = $this->record();
            if ($record->finalized_at || $record->archived_at || $record->project->archived_at) {
                throw ValidationException::withMessages(['billing' => 'Ce document est conservé en lecture seule. Dupliquez le devis ou restaurez le dossier si nécessaire.']);
            }
        } elseif (! empty($this->form['client_project_id'])) {
            if (ClientProject::whereKey($this->form['client_project_id'])->firstOrFail()->archived_at) {
                throw ValidationException::withMessages(['billing' => 'Restaurez ce projet avant de créer un document.']);
            }
        }
    }

    public function addItem(): void
    {
        $this->ensureEditable();
        $settings = CommercialSetting::current();
        $this->items[] = ['name' => '', 'description' => '', 'unit' => 'forfait', 'quantity' => '1', 'price' => '0', 'discount_type' => 'percent', 'discount' => '0', 'vat' => (string) ($settings['vat_enabled'] ? $settings['vat_rate'] : '0'), 'frequency' => 'once', 'period_start' => '', 'period_end' => '', 'optional' => false, 'selected' => true];
    }

    public function removeItem(int $index): void
    {
        $this->ensureEditable();
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function toggleService(int $id): void
    {
        $this->ensureEditable();
        $service = Service::where('active', true)->findOrFail($id);
        if (in_array($id, $this->selectedServices, true)) {
            $this->selectedServices = array_values(array_diff($this->selectedServices, [$id]));
            $this->items = array_values(array_filter($this->items, fn (array $item): bool => ($item['service_id'] ?? null) !== $id));

            return;
        }
        if (count($this->items) === 1 && $this->items[0]['name'] === '') {
            $this->items = [];
        }
        $this->addItem();
        $index = array_key_last($this->items);
        if ($index === null) {
            return;
        }
        $this->items[$index] = [...$this->items[$index], 'service_id' => $id, 'name' => $service->name, 'description' => $service->description ?? '', 'unit' => $service->unit, 'price' => CommercialCalculator::input($service->currentPrice()), 'frequency' => $service->frequency];
        if ($this->kind === 'invoices' && $service->frequency !== 'once') {
            $start = today()->startOfMonth();
            $months = match ($service->frequency) {
                'quarterly' => 3, 'yearly' => 12, default => 1
            };
            $this->items[$index]['period_start'] = $start->format('Y-m-d');
            $this->items[$index]['period_end'] = $start->copy()->addMonths($months)->subDay()->format('Y-m-d');
        }
        $this->selectedServices[] = $id;
    }

    /** @return array{items: array<int, array<string, mixed>>, totals: array<string, mixed>, discount: int, deposit: int} */
    private function calculated(): array
    {
        Validator::make($this->form, ['discount_type' => 'required|in:percent,fixed', 'discount' => 'required|string', 'deposit_type' => 'required|in:none,percent,fixed', 'deposit' => 'required|string'])->validate();
        $calculator = app(CommercialCalculator::class);
        // Settings supply default rates; an explicit rate entered on a draft must apply.
        $items = $calculator->normalize($this->items, true);
        if ($this->kind === 'invoices') {
            foreach ($items as $index => &$item) {
                if ($item['optional'] && ! $item['selected']) {
                    throw ValidationException::withMessages(['billing' => 'Une facture contient uniquement des prestations retenues, pour une période déterminée.']);
                }
                if ($item['frequency'] !== 'once') {
                    $period = ['start' => $this->items[$index]['period_start'] ?? '', 'end' => $this->items[$index]['period_end'] ?? ''];
                    Validator::make($period, ['start' => 'required|date_format:Y-m-d', 'end' => 'required|date_format:Y-m-d|after_or_equal:start'], [
                        'start.required' => 'Indiquez le début de la période facturée pour chaque abonnement.',
                        'end.required' => 'Indiquez la fin de la période facturée pour chaque abonnement.',
                        'start.date_format' => 'La date de début de période doit être valide.',
                        'end.date_format' => 'La date de fin de période doit être valide.',
                        'end.after_or_equal' => 'La fin de la période doit suivre sa date de début.',
                    ])->validate();
                    $item['source_frequency'] = $item['frequency'];
                    $item['frequency'] = 'once';
                    $item['period_start'] = $period['start'];
                    $item['period_end'] = $period['end'];
                }
            }
            unset($item);
        }
        $discount = CommercialCalculator::decimal($this->form['discount']);
        $deposit = $this->form['deposit_type'] === 'none' ? 0 : CommercialCalculator::decimal($this->form['deposit']);
        $totals = $calculator->calculate($items, $this->form['discount_type'], $discount, $this->kind === 'invoices' ? 'none' : $this->form['deposit_type'], $deposit);

        return compact('items', 'totals', 'discount', 'deposit');
    }

    public function save(): void
    {
        $this->ensureEditable();
        $data = $this->validate(['form.client_project_id' => 'required|integer|exists:client_projects,id', 'form.issued_on' => 'required|date', 'form.due_on' => 'required|date|after_or_equal:form.issued_on', 'form.discount_type' => 'required|in:percent,fixed', 'form.deposit_type' => 'required|in:none,percent,fixed', 'form.discount' => 'required|string', 'form.deposit' => 'required|string', 'form.conditions' => 'nullable|string|max:20000', 'form.estimated_delay' => 'nullable|string|max:2000', 'form.invoice_type' => ['required', Rule::in(array_keys(Invoice::TYPES))]])['form'];
        $derived = $this->recordId && $this->record() instanceof Invoice && $this->record()->quote_id;
        $calculated = $derived ? null : $this->calculated();
        DB::transaction(function () use ($data, $calculated): void {
            $record = $this->recordId ? $this->record()->newQuery()->lockForUpdate()->findOrFail($this->recordId) : ($this->kind === 'quotes' ? new Quote : new Invoice);
            if ($record->finalized_at || $record->archived_at) {
                throw ValidationException::withMessages(['billing' => 'Le document vient d’être finalisé ou archivé.']);
            }
            $project = ClientProject::lockForUpdate()->whereKey($record->exists ? $record->client_project_id : $data['client_project_id'])->firstOrFail();
            if ($project->archived_at) {
                throw ValidationException::withMessages(['billing' => 'Ce projet est archivé.']);
            }
            if ($record->exists && (int) $data['client_project_id'] !== $project->id) {
                throw ValidationException::withMessages(['billing' => 'Le projet d’un document enregistré ne peut pas être réattribué.']);
            }
            $billing = app(CommercialBilling::class);
            $attributes = ['issued_on' => $data['issued_on'], 'due_on' => $data['due_on'], 'conditions' => $data['conditions'], 'estimated_delay' => $data['estimated_delay']];
            if ($calculated) {
                $attributes += ['client_project_id' => $project->id, 'items' => $calculated['items'], 'totals' => $calculated['totals'], 'discount_type' => $data['discount_type'], 'discount_value' => $calculated['discount'], 'deposit_type' => $this->kind === 'invoices' ? 'none' : $data['deposit_type'], 'deposit_value' => $this->kind === 'invoices' ? 0 : $calculated['deposit'], 'snapshot' => $record->exists ? $record->snapshot : $billing->snapshot($project)];
                if ($record instanceof Invoice) {
                    $attributes['invoice_type'] = $data['invoice_type'];
                }
            }
            if (! $record->exists && $record instanceof Quote) {
                $attributes['number'] = $billing->number('quote');
            }
            if ($calculated) {
                $attributes['snapshot']['seller']['vat_enabled'] = collect($calculated['items'])->contains(fn (array $item): bool => $item['vat_basis'] > 0);
            }
            $record->fill($attributes)->save();
            ProjectEvent::record($project->id, ($record instanceof Quote ? 'Devis enregistré : ' : 'Brouillon de facture enregistré : ').($record->number ?? '#'.$record->id));
            $this->recordId = $record->id;
        });
        $this->loadRecord();
        session()->flash('success', 'Document enregistré.');
    }

    public function finalize(): void
    {
        $this->ensureEditable();
        $this->save();
        $record = $this->record();
        app(CommercialBilling::class)->finalize($record);
        app(CommercialPdf::class)->billing($record);
        $this->loadRecord();
        session()->flash('success', $this->kind === 'quotes' ? 'Devis marqué envoyé et PDF conservé.' : 'Facture émise. Son contenu et son numéro sont maintenant conservés.');
    }

    public function changeStatus(): void
    {
        abort_unless($this->kind === 'quotes' && $this->recordId, 404);
        $this->validate(['statusChoice' => ['required', Rule::in(array_keys(Quote::STATUSES))]]);
        if ($this->statusChoice === 'sent') {
            $this->finalize();

            return;
        }
        DB::transaction(function (): void {
            $quote = Quote::lockForUpdate()->findOrFail($this->recordId);
            if ($quote->archived_at || $quote->project->archived_at) {
                throw ValidationException::withMessages(['billing' => 'Restaurez le document et le projet avant toute action.']);
            }
            $allowed = $quote->finalized_at ? ['accepted', 'refused', 'expired', 'cancelled'] : ['draft', 'ready', 'cancelled'];
            if ($quote->status === 'accepted' || ! in_array($this->statusChoice, $allowed, true)) {
                throw ValidationException::withMessages(['billing' => 'Envoyez le devis avant de l’accepter. Un devis accepté est conservé.']);
            }
            $quote->update(['status' => $this->statusChoice]);
            ProjectEvent::record($quote->client_project_id, $quote->number.' — '.Quote::STATUSES[$this->statusChoice]);
        });
    }

    public function duplicate(): void
    {
        abort_unless($this->kind === 'quotes', 404);
        $copy = app(CommercialBilling::class)->duplicate(Quote::findOrFail($this->recordId));
        $this->redirectRoute('admin.billing.edit', ['kind' => 'quotes', 'id' => $copy->id]);
    }

    public function archive(): void
    {
        DB::transaction(function (): void {
            $record = $this->record();
            $record->update(['archived_at' => $record->archived_at ? null : now()]);
            ProjectEvent::record($record->client_project_id, ($record->archived_at ? 'Document archivé : ' : 'Document restauré : ').($record->number ?? '#'.$record->id));
        });
    }

    public function generatePdf(): void
    {
        if (! $this->recordId) {
            $this->save();
        }
        $document = app(CommercialPdf::class)->billing($this->record());
        $this->redirectRoute('admin.documents.download', ['document' => $document->id]);
    }

    public function convert(): void
    {
        abort_unless($this->kind === 'quotes', 404);
        if ($this->record()->project->archived_at) {
            throw ValidationException::withMessages(['billing' => 'Restaurez le projet.']);
        }
        $interim = $this->invoiceType === 'interim' ? CommercialCalculator::decimal($this->interimAmount) : null;
        $invoice = app(CommercialBilling::class)->fromQuote(Quote::findOrFail($this->recordId), $this->invoiceType, $interim);
        $this->redirectRoute('admin.billing.edit', ['kind' => 'invoices', 'id' => $invoice->id]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $record = $this->recordId ? $this->record() : null;
        $totals = $record?->totals;
        $previewError = null;
        if ($this->editor && ! $record?->finalized_at && ! ($record instanceof Invoice && $record->quote_id)) {
            try {
                $totals = $this->calculated()['totals'];
            } catch (ValidationException) {
                $totals = null;
                $previewError = 'Complétez les lignes et montants pour calculer l’aperçu.';
            }
        }
        $query = $this->kind === 'quotes' ? Quote::query() : Invoice::query();
        $records = $query->with('project.client')->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
            $term = '%'.mb_substr($this->search, 0, 200).'%';
            $q->where('number', 'like', $term)->orWhereHas('project', fn ($q) => $q->where('name', 'like', $term)->orWhereHas('client', fn ($q) => $q->where('name', 'like', $term)));
        }))->when($this->filter === 'archived', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when($this->filter !== '' && $this->filter !== 'archived', fn ($q) => $q->where('status', $this->filter))->latest()->paginate(15);

        return view('livewire.admin.billing', ['record' => $record, 'records' => $records, 'totals' => $totals, 'previewError' => $previewError, 'previewProject' => ! empty($this->form['client_project_id']) ? ClientProject::with('client')->whereKey($this->form['client_project_id'])->first() : null, 'previewSeller' => $record ? $record->snapshot['seller'] : CommercialSetting::current(), 'projects' => ClientProject::with('client')->whereNull('archived_at')->orderBy('name')->get(), 'services' => Service::where('active', true)->orderBy('category')->orderBy('position')->get(), 'readOnly' => $record && ($record->finalized_at || $record->archived_at || $record->project->archived_at), 'derived' => $record instanceof Invoice && $record->quote_id])->layout('components.admin-layout', ['title' => $this->kind === 'quotes' ? 'Devis' : 'Factures']);
    }
}
