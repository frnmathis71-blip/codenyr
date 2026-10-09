<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\CommercialPdf;
use App\Services\DocumentDownload;
use App\Services\ProjectLegalDocuments;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CommercialDocumentController extends Controller
{
    public function show(Document $document): BinaryFileResponse|Response
    {
        Gate::authorize('admin');
        if (! $document->finalized_at && ! $document->path && $document->content !== null && in_array($document->type, ['terms', 'contract'])) {
            $prepared = app(ProjectLegalDocuments::class)->prepare($document);
            $document->content = $prepared['content'];
            $document->snapshot = $prepared['snapshot'];
        }
        if (! $document->path && $document->content !== null && ! $document->finalized_at) {
            $bytes = app(CommercialPdf::class)->renderDocument($document);

            return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="apercu.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        abort_unless($document->path && Storage::disk('local')->exists($document->path), 404);
        abort_unless(in_array($document->mime, ['application/pdf', 'image/png', 'image/jpeg'], true), 415);

        return response()->file(Storage::disk('local')->path($document->path), ['Content-Type' => $document->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function download(Document $document): BinaryFileResponse
    {
        Gate::authorize('admin');

        return app(DocumentDownload::class)->response($document);
    }

    public function version(DocumentVersion $version): BinaryFileResponse
    {
        Gate::authorize('admin');
        abort_unless(Storage::disk('local')->exists($version->path), 404);

        return response()->download(Storage::disk('local')->path($version->path), 'document-v'.$version->version.'.pdf', ['Content-Type' => $version->mime, 'Cache-Control' => 'private, no-store']);
    }
}
