<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property array<string, mixed>|null $snapshot */
class Document extends Model
{
    public const TYPES = ['quote' => 'Devis', 'invoice' => 'Facture', 'terms' => 'CGV', 'contract' => 'Contrat de prestation', 'maintenance' => 'Contrat de maintenance', 'specification' => 'Cahier des charges', 'order' => 'Bon de commande', 'certificate' => 'Attestation', 'client' => 'Document client', 'legal' => 'Document juridique', 'commercial' => 'Document commercial', 'other' => 'Autre', 'work' => 'Fichier de travail'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'document_date' => 'date', 'archived_at' => 'datetime', 'client_visible' => 'boolean'];
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @param Builder<Document> $query
     * @return Builder<Document>
     */
    public function scopeCustomerReady(Builder $query): Builder
    {
        return $query->whereNull('archived_at')->whereNotNull('path')
            ->where(fn (Builder $q) => $q->whereNull('quote_id')->orWhereHas('quote', fn (Builder $q) => $q->whereNotNull('finalized_at')))
            ->where(fn (Builder $q) => $q->whereNull('invoice_id')->orWhereHas('invoice', fn (Builder $q) => $q->whereNotNull('finalized_at')));
    }

    /** @param Builder<Document> $query
     * @return Builder<Document>
     */
    public function scopeForCustomer(Builder $query, int $userId): Builder
    {
        return $query->customerReady()->where('client_visible', true)
            ->whereHas('project', fn (Builder $q) => $q->where('user_id', $userId));
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<ClientProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    /** @return HasMany<Document, $this> */
    public function signedCopies(): HasMany
    {
        return $this->hasMany(self::class, 'original_id');
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }
}
