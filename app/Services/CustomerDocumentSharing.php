<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\Document;
use App\Models\ProjectEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CustomerDocumentSharing
{
    public function toggle(Document $document): void
    {
        Gate::authorize('admin');
        DB::transaction(function () use ($document): void {
            // Use the same lock order as project reassignment.
            $project = ClientProject::lockForUpdate()->find($document->client_project_id);
            $document = Document::lockForUpdate()->findOrFail($document->id);
            if (! $document->client_visible && (! $project?->user_id || ! Document::customerReady()->whereKey($document->id)->exists() || ! Storage::disk('local')->exists($document->path))) {
                throw ValidationException::withMessages(['sharing' => 'Rattachez un compte au projet et préparez un fichier disponible. Les brouillons de devis et factures ne peuvent pas être partagés.']);
            }
            $document->update(['client_visible' => ! $document->client_visible]);
            if ($project) {
                ProjectEvent::record($project->id, ($document->client_visible ? 'Document partagé dans l’espace client : ' : 'Document retiré de l’espace client : ').$document->name);
            }
        });
    }
}
