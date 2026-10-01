<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonInterface|null $starts_on
 * @property CarbonInterface|null $due_on
 * @property CarbonInterface|null $archived_at
 */
class ClientProject extends Model
{
    public const STATUSES = ['prospect' => 'Prospect', 'quote_to_prepare' => 'Devis à préparer', 'quote_sent' => 'Devis envoyé', 'waiting_client' => 'En attente client', 'quote_accepted' => 'Devis accepté', 'waiting_deposit' => 'En attente d’acompte', 'ready' => 'À démarrer', 'development' => 'En développement', 'validation' => 'En validation', 'launch' => 'À mettre en ligne', 'completed' => 'Terminé', 'maintenance' => 'En maintenance', 'cancelled' => 'Annulé'];

    public const TYPES = ['Landing Page', 'Site vitrine', 'Site Pro', 'E-commerce', 'Application web', 'Refonte', 'Maintenance', 'Développement spécifique', 'Autre'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'due_on' => 'date', 'archived_at' => 'datetime'];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<MaintenanceContract, $this> */
    public function maintenance(): HasMany
    {
        return $this->hasMany(MaintenanceContract::class);
    }

    /** @return HasMany<ProjectNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class);
    }

    /** @return HasMany<ProjectEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ProjectEvent::class);
    }

    /** @return array<string, bool> */
    public function checklist(): array
    {
        $documents = $this->documents()->whereNull('archived_at')->get();
        $has = fn (string $type): bool => $documents->contains(fn (Document $document): bool => $document->type === $type);
        $signed = fn (string $type): bool => $documents->contains(fn (Document $document): bool => $document->type === $type && $document->original_id !== null);
        $invoices = $this->invoices()->whereNotNull('finalized_at')->with('payments')->get();
        $paid = fn (string $type): bool => $invoices->contains(fn (Invoice $invoice): bool => $invoice->invoice_type === $type && $invoice->remainingCents() === 0);
        $checks = ['Devis créé' => $this->quotes()->exists(), 'Devis accepté' => $this->quotes()->where('status', 'accepted')->exists(), 'CGV' => $has('terms'), 'CGV signées' => $signed('terms'), 'Contrat de prestation' => $has('contract'), 'Contrat signé' => $signed('contract')];
        $needsDeposit = $this->quotes()->where('status', 'accepted')->get()->contains(fn (Quote $quote): bool => ($quote->totals['deposit'] ?? 0) > 0);
        if ($needsDeposit) {
            $checks['Facture d’acompte'] = $invoices->contains('invoice_type', 'deposit');
            $checks['Acompte reçu'] = $paid('deposit');
        }
        $checks['Facture finale'] = $invoices->contains(fn (Invoice $invoice): bool => in_array($invoice->invoice_type, ['standard', 'balance'], true));
        $checks['Solde reçu'] = $paid('standard') || $paid('balance');
        if ($this->maintenance()->where('status', '!=', 'archived')->exists()) {
            $checks['Contrat de maintenance'] = $has('maintenance');
            $checks['Maintenance signée'] = $signed('maintenance');
        }

        return $checks;
    }

    /** @return list<string> */
    public function alerts(): array
    {
        $settings = CommercialSetting::current();
        $alerts = [];
        if (! $this->quotes()->exists()) {
            $alerts[] = 'Devis pas encore créé';
        }
        if ($this->quotes()->where('status', 'sent')->where('sent_at', '<=', now()->subDays((int) $settings['quote_followup_days']))->exists()) {
            $alerts[] = 'Devis envoyé depuis au moins '.$settings['quote_followup_days'].' jours sans réponse';
        }
        if ($this->quotes()->where('status', 'accepted')->exists()) {
            foreach ($this->checklist() as $name => $present) {
                if (! $present && in_array($name, ['CGV signées', 'Contrat signé', 'Acompte reçu', 'Maintenance signée'], true)) {
                    $alerts[] = $name.' : à récupérer';
                }
            }
        }
        if ($this->invoices()->whereNotNull('finalized_at')->whereDate('due_on', '<', today())->get()->contains(fn (Invoice $invoice): bool => $invoice->remainingCents() > 0)) {
            $alerts[] = 'Facture échue non soldée';
        }
        if ($this->due_on && $this->due_on->between(today(), today()->addDays((int) $settings['delivery_alert_days'])) && ! in_array($this->status, ['completed', 'cancelled', 'maintenance'], true)) {
            $alerts[] = 'Livraison prévue dans les '.$settings['delivery_alert_days'].' prochains jours';
        }

        return $alerts;
    }
}
