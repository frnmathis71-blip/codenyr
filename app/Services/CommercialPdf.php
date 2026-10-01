<?php

namespace App\Services;

use App\Models\BillingRecord;
use App\Models\Document;
use App\Models\ProjectEvent;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CommercialPdf
{
    public function billing(BillingRecord $record): Document
    {
        $path = null;
        try {
            return DB::transaction(function () use ($record, &$path): Document {
                $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                $kind = $record instanceof Quote ? 'quote' : 'invoice';
                $fingerprint = hash('sha256', ($record->finalized_at ? '' : 'quote-layout-v2:').json_encode($record->only(['number', 'items', 'totals', 'snapshot', 'conditions', 'estimated_delay', 'issued_on', 'due_on', 'finalized_at']), JSON_THROW_ON_ERROR));
                $document = Document::where($kind.'_id', $record->id)->where('type', $kind)->whereNull('original_id')->first();
                if ($document && ($document->snapshot['fingerprint'] ?? '') === $fingerprint && $document->path && Storage::disk('local')->exists($document->path)) {
                    return $document;
                }
                $bytes = Pdf::loadView('commercial.billing-pdf', ['record' => $record, 'kind' => $kind])->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4')->output();
                $path = 'client-projects/'.$record->client_project_id.'/'.$kind.'s/'.Str::uuid().'.pdf';
                if (! Storage::disk('local')->put($path, $bytes)) {
                    throw new RuntimeException('Le PDF n’a pas pu être enregistré.');
                }
                $document ??= new Document;
                $document->fill(['client_id' => $document->client_id ?? $record->snapshot['client']['id'] ?? $record->project->client_id, 'client_project_id' => $record->client_project_id, $kind.'_id' => $record->id, 'name' => ($record->number ?? 'Brouillon facture #'.$record->id).'.pdf', 'type' => $kind, 'document_date' => $record->issued_on, 'path' => $path, 'mime' => 'application/pdf', 'size' => strlen($bytes), 'snapshot' => [...$record->snapshot, 'fingerprint' => $fingerprint]])->save();
                $document->versions()->create(['version' => (int) $document->versions()->max('version') + 1, 'path' => $path, 'mime' => 'application/pdf', 'size' => strlen($bytes)]);
                ProjectEvent::record($record->client_project_id, 'PDF généré : '.$document->name);

                return $document;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function document(Document $document): Document
    {
        $path = null;
        try {
            return DB::transaction(function () use ($document, &$path): Document {
                $document = Document::lockForUpdate()->findOrFail($document->id);
                if ($document->path && Storage::disk('local')->exists($document->path)) {
                    return $document;
                }
                abort_unless($document->content !== null, 422);
                if (in_array($document->type, ['terms', 'contract']) && str_contains($document->content, '[À compléter')) {
                    throw ValidationException::withMessages(['document' => 'Complétez les mentions manquantes avant de générer le PDF définitif. L’aperçu reste disponible.']);
                }
                $bytes = Pdf::loadView('commercial.document-pdf', ['document' => $document])->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4')->output();
                $path = 'client-projects/'.($document->client_project_id ?? 'client-'.$document->client_id).'/legal/'.Str::uuid().'.pdf';
                if (! Storage::disk('local')->put($path, $bytes)) {
                    throw new RuntimeException('Le PDF n’a pas pu être enregistré.');
                }
                $document->update(['path' => $path, 'mime' => 'application/pdf', 'size' => strlen($bytes), 'status' => 'generated']);
                $document->versions()->create(['version' => 1, 'path' => $path, 'mime' => 'application/pdf', 'size' => strlen($bytes)]);
                if ($document->client_project_id) {
                    ProjectEvent::record($document->client_project_id, 'PDF généré : '.$document->name);
                }

                return $document;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }
}
