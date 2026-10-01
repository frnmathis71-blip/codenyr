<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerDocumentController extends Controller
{
    public function download(int $document): BinaryFileResponse
    {
        $file = Document::forCustomer((int) auth()->id())->findOrFail($document);
        abort_unless(Storage::disk('local')->exists($file->path), 404);
        $filename = preg_replace('/[^\pL\pN ._-]/u', '-', $file->original_name ?: $file->name) ?: 'document';
        if (! $file->original_name && $file->mime === 'application/pdf' && ! str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return response()->download(Storage::disk('local')->path($file->path), $filename, ['Content-Type' => $file->mime ?? 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
