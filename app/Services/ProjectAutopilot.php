<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\CommercialSetting;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProjectEvent;
use App\Models\Quote;
use App\Support\TestMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Drives a client project from quote to final payment: works out the next step and
 * performs it with the existing services (numbering, frozen PDFs, billing rules).
 */
class ProjectAutopilot
{
    public const STEPS = [
        'quote' => 'Devis préparé',
        'sent' => 'Devis envoyé',
        'accepted' => 'Devis accepté',
        'kit' => 'Documents de démarrage',
        'signed' => 'Signatures reçues',
        'deposit' => 'Acompte encaissé',
        'delivered' => 'Site livré',
        'final' => 'Facture de solde',
        'paid' => 'Solde encaissé',
    ];

    /** @return array<string, bool> step => done */
    public function progress(ClientProject $project): array
    {
        $project->loadMissing(['quotes', 'invoices.payments', 'documents']);
        $accepted = $this->acceptedQuote($project);
        $legal = $this->legalDocuments($project);
        $deposit = $this->depositInvoice($project);
        $final = $project->invoices->first(fn (Invoice $invoice): bool => in_array($invoice->invoice_type, ['standard', 'balance'], true) && $invoice->finalized_at !== null);
        $needsDeposit = $accepted && ($accepted->totals['deposit'] ?? 0) > 0;

        $steps = [
            'quote' => $project->quotes->isNotEmpty(),
            'sent' => $project->quotes->contains(fn (Quote $quote): bool => $quote->finalized_at !== null),
            'accepted' => $accepted !== null,
            'kit' => $accepted !== null && $legal['terms'] && $legal['contract'] && (! $needsDeposit || $deposit?->finalized_at !== null),
            'signed' => (bool) ($legal['terms']?->signedCopies->isNotEmpty() && $legal['contract']?->signedCopies->isNotEmpty()),
            'deposit' => ! $needsDeposit || ($deposit?->finalized_at !== null && $deposit->remainingCents() === 0),
            'delivered' => in_array($project->status, ['launch', 'completed', 'maintenance'], true),
            'final' => $final !== null,
            'paid' => $final !== null && $final->remainingCents() === 0,
        ];
        if (! $needsDeposit) {
            unset($steps['deposit']);
        }

        return $steps;
    }

    public function current(ClientProject $project): ?string
    {
        foreach ($this->progress($project) as $step => $done) {
            if (! $done) {
                return $step;
            }
        }

        return null;
    }

    public function acceptedQuote(ClientProject $project): ?Quote
    {
        return $project->quotes->where('status', 'accepted')->whereNull('archived_at')->sortByDesc('id')->first();
    }

    /** @return array{terms: ?Document, contract: ?Document} Finalized originals (not signed copies). */
    public function legalDocuments(ClientProject $project): array
    {
        $documents = $project->documents->whereNull('archived_at')->whereNull('original_id')->whereNotNull('finalized_at');
        $documents->loadMissing('signedCopies');

        return ['terms' => $documents->where('type', 'terms')->sortByDesc('id')->first(), 'contract' => $documents->where('type', 'contract')->sortByDesc('id')->first()];
    }

    public function depositInvoice(ClientProject $project): ?Invoice
    {
        return $project->invoices->where('invoice_type', 'deposit')->whereNull('archived_at')->sortByDesc('id')->first();
    }

    /** Records the client's answer on a sent quote, with the same rules as the quote editor. */
    public function answerQuote(Quote $quote, string $status): void
    {
        abort_unless(in_array($status, ['accepted', 'refused'], true), 422);
        DB::transaction(function () use ($quote, $status): void {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            if (! $quote->finalized_at || $quote->status !== 'sent' || $quote->archived_at || $quote->project->archived_at) {
                throw ValidationException::withMessages(['autopilot' => 'Seul un devis envoyé peut être accepté ou refusé.']);
            }
            $quote->update(['status' => $status]);
            $quote->project->update(['status' => $status === 'accepted' ? 'quote_accepted' : 'quote_to_prepare']);
            if ($status === 'accepted' && $quote->project->lead_id) {
                Lead::whereKey($quote->project->lead_id)->update(['status' => 'accepted']);
            }
            ProjectEvent::record($quote->client_project_id, $quote->number.' — '.Quote::STATUSES[$status]);
        });
    }

    /**
     * One click after acceptance: completes what can be deduced, then creates and
     * finalizes the CGV and the contract, and issues the deposit invoice.
     *
     * @return array{done: list<string>, missing: list<string>}
     */
    public function startKit(ClientProject $project): array
    {
        $project->loadMissing(['client', 'quotes', 'invoices', 'documents']);
        $quote = $this->acceptedQuote($project) ?? throw ValidationException::withMessages(['autopilot' => 'Le devis doit d’abord être accepté.']);
        $client = $project->client;
        if (TestMode::enabled() && blank($client->audience)) {
            $client->update(['audience' => 'professional']);
        }
        if (! in_array($client->audience, ['professional', 'consumer'], true)) {
            throw ValidationException::withMessages(['autopilot' => 'Indiquez si le client est un professionnel ou un particulier.']);
        }

        // Deducible information, filled only where it is empty.
        $client->fill(array_filter([
            'country' => blank($client->country) ? 'France' : null,
            'signatory' => blank($client->signatory) ? ($client->contact ?: $client->name) : null,
            'signatory_role' => blank($client->signatory_role) && $client->audience === 'professional' ? 'Représentant légal' : null,
        ]))->save();
        $type = (string) $project->type;
        $project->fill(array_filter([
            'starts_on' => $project->starts_on ? null : today(),
            'due_on' => $project->due_on ? null : today()->addDays(StandardBusinessProfile::delayDays($type)),
            'status' => in_array($project->status, ['prospect', 'quote_to_prepare', 'quote_sent', 'waiting_client', 'quote_accepted'], true) ? 'waiting_deposit' : null,
        ]))->save();
        $terms = $this->ensureTermsVersion();

        $done = $missing = [];
        $existing = $this->legalDocuments($project->fresh(['documents']));
        foreach (['terms', 'contract'] as $docType) {
            if ($existing[$docType]) {
                continue;
            }
            // A draft left by an earlier attempt is completed and finalized instead of duplicated.
            $draft = $project->documents()->where('type', $docType)->whereNull('original_id')->whereNull('archived_at')->whereNull('finalized_at')->latest('id')->first();
            if ($draft) {
                $errors = TestMode::enabled() ? [] : app(ProjectLegalDocuments::class)->prepare($draft)['errors'];
                $errors ? array_push($missing, ...array_values($errors)) : app(CommercialPdf::class)->document($draft);
                $errors ?: $done[] = Document::TYPES[$docType].' généré';

                continue;
            }
            $result = $this->createLegalDocument($project->fresh(['client', 'maintenance']), $quote, $docType, $terms);
            $result['missing'] ? array_push($missing, ...$result['missing']) : $done[] = Document::TYPES[$docType].' généré';
        }

        if (($quote->totals['deposit'] ?? 0) > 0) {
            $deposit = $this->depositInvoice($project->fresh(['invoices']));
            if (! $deposit?->finalized_at) {
                $deposit ??= app(CommercialBilling::class)->fromQuote($quote, 'deposit');
                $this->issue($deposit);
                $done[] = 'Facture d’acompte '.$deposit->fresh()->number.' émise';
            }
        }

        return ['done' => $done, 'missing' => array_values(array_unique($missing))];
    }

    /** Active CGV version, created from the standard profile when none applies yet. */
    public function ensureTermsVersion(): DocumentTemplate
    {
        $date = today()->format('Y-m-d');
        $terms = app(TermsVersions::class)->applicable($date);
        if ($terms) {
            return $terms;
        }
        $template = DocumentTemplate::create(['name' => 'CGV Codenyr', 'type' => 'terms', 'content' => StandardBusinessProfile::termsContent(), 'revision' => 1, 'effective_on' => $date, 'status' => 'draft']);
        app(TermsVersions::class)->transition($template, 'active');

        return $template->fresh();
    }

    /** @return array{document: Document, missing: list<string>} */
    private function createLegalDocument(ClientProject $project, Quote $quote, string $type, DocumentTemplate $terms): array
    {
        $settings = CommercialSetting::current();
        // Per-document fallbacks for what an older quote may lack; the quote itself stays untouched.
        $input = array_filter([
            'terms_template_id' => (string) $terms->id,
            'quote_estimated_delay' => blank($quote->estimated_delay) ? StandardBusinessProfile::estimatedDelay((string) $project->type) : null,
            'quote_conditions' => blank($quote->conditions) ? $settings['quote_mention'] : null,
            'object' => blank($project->description) ? 'Création du site « '.$project->name.' » ('.$project->type.'), conformément au devis '.$quote->number.'.' : null,
            // Often unknown when signing: stated as the client's choice in this document only.
            'project_domain' => blank($project->domain) ? 'Nom de domaine choisi par le client' : null,
            'project_host' => blank($project->host) ? 'Hébergeur choisi par le client' : null,
        ], fn ($value) => $value !== null);

        $candidate = new Document(['client_id' => $project->client_id, 'client_project_id' => $project->id, 'quote_id' => $quote->id, 'type' => $type, 'document_date' => today(), 'contract_data' => $input]);
        $legal = app(ProjectLegalDocuments::class)->prepare($candidate);
        $snapshot = array_replace(app(CommercialBilling::class)->snapshot($project), ['audience' => $project->client->audience], $legal['snapshot']);

        $document = DB::transaction(function () use ($project, $quote, $type, $input, $legal, $snapshot): Document {
            $document = Document::create(['contract_data' => $input, 'name' => Document::TYPES[$type].' — '.$project->name, 'type' => $type, 'client_id' => $project->client_id, 'client_project_id' => $project->id, 'quote_id' => $quote->id, 'document_date' => today(), 'content' => $legal['content'], 'snapshot' => $snapshot, 'status' => 'draft']);
            ProjectEvent::record($project->id, 'Document préparé automatiquement : '.$document->name);

            return $document;
        });

        if ($legal['errors'] && ! TestMode::enabled()) {
            return ['document' => $document, 'missing' => array_values($legal['errors'])];
        }
        app(CommercialPdf::class)->document($document);

        return ['document' => $document, 'missing' => []];
    }

    /** Finalizes a draft invoice and stores its PDF, exactly like the invoice editor. */
    public function issue(Invoice $invoice): void
    {
        app(CommercialBilling::class)->finalize($invoice);
        app(CommercialPdf::class)->billing($invoice->fresh());
    }

    /** Records the full remaining amount as received today by bank transfer. */
    public function markPaid(Invoice $invoice): void
    {
        app(InvoicePayments::class)->record($invoice->id, ['amount' => number_format($invoice->remainingCents() / 100, 2, ',', ''), 'paid_on' => today()->format('Y-m-d'), 'method' => 'transfer', 'reference' => '', 'comment' => 'Solde enregistré depuis le pilote du dossier'], (string) Str::uuid());
    }

    public function markDelivered(ClientProject $project): void
    {
        DB::transaction(function () use ($project): void {
            $project->update(['status' => 'completed']);
            if ($project->lead_id) {
                Lead::whereKey($project->lead_id)->whereNull('delivered_at')->update(['delivered_at' => now()]);
            }
            ProjectEvent::record($project->id, 'Site livré au client');
        });
    }

    /** Creates and issues the final invoice (balance after a deposit, or the full amount). */
    public function finalInvoice(ClientProject $project): Invoice
    {
        $project->loadMissing(['quotes', 'invoices']);
        $quote = $this->acceptedQuote($project) ?? throw ValidationException::withMessages(['autopilot' => 'Aucun devis accepté à facturer.']);
        $issued = $project->invoices->where('quote_id', $quote->id)->whereNotNull('finalized_at');
        $draft = $project->invoices->where('quote_id', $quote->id)->whereNull('finalized_at')->whereNull('archived_at')->first();
        $invoice = $draft ?? app(CommercialBilling::class)->fromQuote($quote, $issued->isEmpty() ? 'standard' : 'balance');
        $this->issue($invoice);

        return $invoice->fresh();
    }
}
