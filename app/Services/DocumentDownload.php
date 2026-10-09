<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentDownload
{
    /** The caller must authorize access before preparing the response. */
    public function response(Document $document): BinaryFileResponse
    {
        abort_unless($document->path && Storage::disk('local')->exists($document->path), 404);
        $filename = preg_replace('/[^\pL\pN ._-]/u', '-', $document->original_name ?: $document->name) ?: 'document';
        if (! $document->original_name && $document->mime === 'application/pdf' && ! str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return response()->download(Storage::disk('local')->path($document->path), $filename, ['Content-Type' => $document->mime ?? 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
