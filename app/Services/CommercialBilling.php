<?php

namespace App\Services;

use App\Models\BillingRecord;
use App\Models\ClientProject;
use App\Models\CommercialSetting;
use App\Models\Invoice;
use App\Models\ProjectEvent;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialBilling
{
    public function number(string $kind): string
    {
        $year = (int) now()->year;
        DB::table('document_sequences')->insertOrIgnore(['kind' => $kind, 'year' => $year, 'value' => 0]);
        $sequence = DB::table('document_sequences')->where('kind', $kind)->where('year', $year)->lockForUpdate()->first();
        abort_unless($sequence !== null, 500);
        $next = $sequence->value + 1;
        DB::table('document_sequences')->where('id', $sequence->id)->update(['value' => $next]);
        $settings = CommercialSetting::current();

        return $settings[$kind.'_prefix'].'-'.$year.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    public function snapshot(ClientProject $project): array
    {
        return ['seller' => CommercialSetting::current(), 'client' => $project->client->only(['id', 'name', 'contact', 'email', 'phone', 'address', 'registration']), 'project' => $project->only(['name', 'type', 'website_url', 'domain', 'starts_on', 'due_on'])];
    }

    public function finalize(BillingRecord $record): void
    {
        DB::transaction(function () use ($record): void {
            $locked = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            if ($locked->finalized_at) {
                return;
            }
            if ($locked->archived_at || $locked->project->archived_at || ! in_array($locked->status, ['draft', 'ready'], true)) {
                throw ValidationException::withMessages(['billing' => 'Seul un brouillon actif peut être finalisé.']);
            }
            if ($locked instanceof Invoice && $locked->quote_id) {
                $quote = Quote::lockForUpdate()->findOrFail($locked->quote_id);
                if ($quote->status !== 'accepted') {
                    throw ValidationException::withMessages(['billing' => 'Le devis source doit être accepté.']);
                }
                $billed = Invoice::where('quote_id', $quote->id)->whereNotNull('finalized_at')->get()->sum(fn (Invoice $invoice): int => $invoice->totals['initial']['ttc']);
                if ($billed + $locked->totals['initial']['ttc'] > $quote->totals['initial']['ttc']) {
                    throw ValidationException::withMessages(['billing' => 'Cette facture dépasserait le montant initial du devis. Recréez le solde après les autres émissions.']);
                }
            }
            $locked->update(['number' => $locked->number ?? $this->number($locked instanceof Quote ? 'quote' : 'invoice'), 'status' => $locked instanceof Quote ? 'sent' : 'issued', 'finalized_at' => now(), 'sent_at' => $locked instanceof Quote ? now() : null]);
            ProjectEvent::record($locked->client_project_id, ($locked instanceof Quote ? 'Devis envoyé : ' : 'Facture émise : ').$locked->number);
        });
        $record->refresh();
    }

    public function duplicate(Quote $quote): Quote
    {
        if ($quote->project->archived_at) {
            throw ValidationException::withMessages(['billing' => 'Restaurez le projet avant de dupliquer le devis.']);
        }

        return DB::transaction(function () use ($quote): Quote {
            $copy = $quote->replicate(['number', 'finalized_at', 'sent_at', 'archived_at']);
            $copy->status = 'draft';
            $copy->number = $this->number('quote');
            $copy->issued_on = today();
            $copy->due_on = today()->addDays((int) CommercialSetting::current()['validity_days']);
            $copy->save();
            ProjectEvent::record($copy->client_project_id, 'Devis dupliqué : '.$copy->number);

            return $copy;
        });
    }

    public function fromQuote(Quote $quote, string $type, ?int $interimCents = null): Invoice
    {
        return DB::transaction(function () use ($quote, $type, $interimCents): Invoice {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            if ($quote->status !== 'accepted' || $quote->archived_at || $quote->project->archived_at || ! in_array($type, array_keys(Invoice::TYPES), true)) {
                throw ValidationException::withMessages(['billing' => 'Sélectionnez un devis accepté et un type de facture valide.']);
            }
            $existing = Invoice::where('quote_id', $quote->id)->whereNull('finalized_at')->first();
            if ($existing) {
                return $existing;
            }
            $issued = Invoice::where('quote_id', $quote->id)->whereNotNull('finalized_at')->get();
            if ($type === 'standard' && $issued->isNotEmpty()) {
                throw ValidationException::withMessages(['billing' => 'Des factures ont déjà été émises. Utilisez une facture intermédiaire ou de solde.']);
            }
            $remaining = $quote->totals['initial']['ttc'] - $issued->sum(fn (Invoice $invoice): int => $invoice->totals['initial']['ttc']);
            if ($remaining <= 0) {
                throw ValidationException::withMessages(['billing' => 'Ce devis a déjà été entièrement facturé.']);
            }
            if ($type === 'deposit' && $issued->contains('invoice_type', 'deposit')) {
                throw ValidationException::withMessages(['billing' => 'Un acompte a déjà été facturé.']);
            }
            $partial = in_array($type, ['deposit', 'interim'], true);
            $ratio = match ($type) {
                'deposit' => ($quote->totals['deposit'] ?? 0), 'interim' => $interimCents ?? 0, default => $remaining
            };
            if ($ratio <= 0 || $ratio > $remaining) {
                throw ValidationException::withMessages(['billing' => 'Le montant de l’acompte ou de la facture intermédiaire doit être positif et ne pas dépasser le reste à facturer.']);
            }
            $remainingTax = $quote->totals['initial']['vat'] - $issued->sum(fn (Invoice $invoice): int => $invoice->totals['initial']['vat']);
            $targetTax = CommercialCalculator::rounded($remainingTax * $ratio, $remaining);
            $netBudget = $ratio - $targetTax;
            $sourceNetRemaining = $remaining - $remainingTax;
            $items = [];
            foreach ($quote->totals['initial']['rows'] as $index => $row) {
                $already = 0;
                foreach ($issued as $invoice) {
                    foreach ($invoice->items as $item) {
                        if (($item['source_index'] ?? null) === (int) $index) {
                            $already += $item['price_cents'];
                        }
                    }
                }
                $net = max(0, $row['net'] - $already);
                if ($partial) {
                    $allocation = $sourceNetRemaining > 0 ? CommercialCalculator::rounded($netBudget * $net, $sourceNetRemaining) : 0;
                    $sourceNetRemaining -= $net;
                    $netBudget -= $allocation;
                    $net = $allocation;
                }
                $source = $quote->items[$index];
                $items[] = [...$source, 'source_index' => (int) $index, 'name' => ($type === 'deposit' ? 'Acompte — ' : '').$source['name'], 'quantity_units' => 100, 'price_cents' => $net, 'discount_value' => 0, 'discount_type' => 'percent', 'optional' => false, 'selected' => true];
            }
            $totals = app(CommercialCalculator::class)->calculate($items);
            if ($type === 'standard') {
                $items = [];
                foreach ($quote->items as $index => $source) {
                    if ($source['frequency'] === 'once' && (! $source['optional'] || $source['selected'])) {
                        $items[] = [...$source, 'source_index' => $index];
                    }
                }
                $totals = app(CommercialCalculator::class)->calculate($items, $quote->discount_type, $quote->discount_value);
            }
            // Preserve exact VAT residuals by rate on a balance invoice.
            if ($partial) {
                $totals['initial']['vat'] = $targetTax;
                $totals['initial']['ttc'] = $ratio;
                $totals['remaining'] = $ratio;
            } else {
                $totals['initial']['vat'] = $quote->totals['initial']['vat'] - $issued->sum(fn (Invoice $invoice): int => $invoice->totals['initial']['vat']);
                $totals['initial']['ttc'] = $totals['initial']['ht'] + $totals['initial']['vat'];
                $totals['remaining'] = $totals['initial']['ttc'];
            }
            $invoice = Invoice::create(['client_project_id' => $quote->client_project_id, 'quote_id' => $quote->id, 'invoice_type' => $type, 'status' => 'draft', 'issued_on' => today(), 'due_on' => today()->addDays((int) CommercialSetting::current()['payment_days']), 'items' => $items, 'snapshot' => $quote->snapshot, 'totals' => $totals, 'discount_type' => $type === 'standard' ? $quote->discount_type : 'percent', 'discount_value' => $type === 'standard' ? $quote->discount_value : 0, 'deposit_type' => 'none', 'conditions' => CommercialSetting::current()['invoice_mention']]);
            ProjectEvent::record($quote->client_project_id, 'Facture '.$type.' préparée depuis '.$quote->number);

            return $invoice;
        });
    }
}
