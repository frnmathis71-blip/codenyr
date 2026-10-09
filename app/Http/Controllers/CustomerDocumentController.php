<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentDownload;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerDocumentController extends Controller
{
    public function download(int $document): BinaryFileResponse
    {
        $file = Document::forCustomer((int) auth()->id())->findOrFail($document);

        return app(DocumentDownload::class)->response($file);
    }
}
