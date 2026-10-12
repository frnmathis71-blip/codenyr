<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\Quote;

class DocumentAutofill
{
    /** @return array{client_id: int|string, quote_id: int|string, terms_template_id: string} */
    public function context(?ClientProject $project, string $date): array
    {
        $quotes = $project?->quotes()->where('status', 'accepted')->whereNotNull('finalized_at')->whereNull('archived_at')->get()
            ->filter(fn (Quote $quote): bool => (int) ($quote->snapshot['client']['id'] ?? $project->client_id) === $project->client_id);
        $terms = validator(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->passes()
            ? app(TermsVersions::class)->applicable($date) : null;

        return ['client_id' => $project->client_id ?? '', 'quote_id' => $quotes?->count() === 1 ? $quotes->first()->id : '', 'terms_template_id' => $terms ? (string) $terms->id : ''];
    }
}
