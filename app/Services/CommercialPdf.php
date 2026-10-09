<?php

namespace App\Services;

use App\Models\BillingRecord;
use App\Models\Document;
use App\Models\ProjectEvent;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CommercialPdf
{
    public function renderDocument(Document $document): string
    {
        return $this->render('commercial.document-pdf', ['document' => $document]);
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): string
    {
        return Pdf::loadView($view, $data)->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4')->output();
    }

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
                $bytes = $this->render('commercial.billing-pdf', ['record' => $record, 'kind' => $kind]);
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

    public function document(Document $document, bool $finalize = true, ?string $reviewHash = null): Document
    {
        Gate::authorize('admin');
        $path = null;
        try {
            return DB::transaction(function () use ($document, $finalize, $reviewHash, &$path): Document {
                $document = Document::lockForUpdate()->findOrFail($document->id);
                if ($document->finalized_at || $document->original_id || ($document->path && ! $document->contract_data && ! in_array($document->type, ['terms', 'contract']))) {
                    return $document;
                }
                // Existing stored files remain historical; migration never promotes them.
                if ($document->path && $document->contract_data === null) {
                    return $document;
                }
                abort_unless($document->content !== null && ! $document->archived_at && ! $document->project?->archived_at, 422);
                $legal = in_array($document->type, ['terms', 'contract']);
                if ($legal) {
                    $result = app(ProjectLegalDocuments::class)->prepare($document);
                    if ($finalize && $result['errors']) {
                        throw ValidationException::withMessages(['document' => ['Impossible de finaliser le document.', ...array_values($result['errors'])]]);
                    }
                    if ($finalize && $reviewHash !== null && ! hash_equals($reviewHash, hash('sha256', json_encode($result['snapshot'], JSON_THROW_ON_ERROR)))) {
                        throw ValidationException::withMessages(['document' => 'Les sources ont changé. Relancez la vérification du document.']);
                    }
                    $document->content = $result['content'];
                    $document->snapshot = array_replace($document->snapshot ?? [], $result['snapshot']);
                    if ($finalize) {
                        $authorId = auth()->user()?->id;
                        abort_unless($authorId !== null && $authorId > 0, 403);
                        $document->finalized_at = now();
                        $document->finalized_by = $authorId;
                    }
                    $logoPath = $document->snapshot['seller']['logo_path'] ?? '';
                    $logo = $logoPath && Storage::disk('local')->exists($logoPath) ? Storage::disk('local')->get($logoPath) : file_get_contents(public_path('images/codenyr.png'));
                    if (! is_string($logo) || $logo === '') {
                        throw new RuntimeException('Le logo du document ne peut pas être lu.');
                    }
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($logo);
                    $document->snapshot = [...$document->snapshot, 'logo_data' => 'data:'.$mime.';base64,'.base64_encode($logo)];
                }
                $bytes = $this->renderDocument($document);
                $path = 'client-projects/'.($document->client_project_id ?? 'client-'.$document->client_id).'/legal/'.Str::uuid().'.pdf';
                if (! Storage::disk('local')->put($path, $bytes)) {
                    throw new RuntimeException('Le PDF n’a pas pu être enregistré.');
                }
                $document->fill(['path' => $path, 'mime' => 'application/pdf', 'size' => strlen($bytes), 'status' => $legal && ! $finalize ? 'draft' : 'generated', 'pdf_sha256' => $legal && $finalize ? hash('sha256', $bytes) : null])->save();
                $document->versions()->create(['version' => (int) $document->versions()->max('version') + 1, 'path' => $path, 'mime' => 'application/pdf', 'size' => strlen($bytes)]);
                if ($document->client_project_id) {
                    ProjectEvent::record($document->client_project_id, ($legal && $finalize ? 'Document finalisé : ' : 'PDF généré : ').$document->name);
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
