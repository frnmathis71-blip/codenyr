<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TermsVersions
{
    public function applicable(string $date): ?DocumentTemplate
    {
        return DocumentTemplate::where('type', 'terms')->where('status', 'active')->whereDate('effective_on', '<=', $date)->orderByDesc('effective_on')->orderByDesc('id')->first();
    }

    public function transition(DocumentTemplate $template, string $status): void
    {
        DB::transaction(function () use ($template, $status): void {
            // One shared lock serializes activation, including on MySQL.
            DB::table('document_sequences')->insertOrIgnore(['kind' => 'terms_activation', 'year' => 0, 'value' => 0]);
            DB::table('document_sequences')->where('kind', 'terms_activation')->lockForUpdate()->first();
            $template = DocumentTemplate::lockForUpdate()->findOrFail($template->id);
            if ($template->type !== 'terms' || ! in_array($status, ['active', 'archived'], true)) {
                throw ValidationException::withMessages(['template' => 'Choisissez une version de CGV et un statut valide.']);
            }
            if ($status === 'active') {
                if (! $template->effective_on || (! filled($template->content) && ! collect($template->parameters ?? [])->contains(fn ($value) => filled($value)))) {
                    throw ValidationException::withMessages(['template' => 'Renseignez la date d’effet et les clauses avant l’activation.']);
                }
                DocumentTemplate::where('type', 'terms')->where('status', 'active')->whereDate('effective_on', $template->effective_on)->where('id', '!=', $template->id)->update(['status' => 'archived']);
            }
            $template->update(['status' => $status]);
        });
    }
}
