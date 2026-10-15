<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">@include('commercial.pdf-style')</head><body>
@php
    $snapshot = $document->snapshot ?? [];
    $seller = $snapshot['seller'] ?? [];
    $client = $snapshot['client'] ?? [];
    $signable = in_array($document->type, ['terms', 'contract', 'maintenance'], true);
    $place = $snapshot['fields']['signature_place'] ?? ($seller['contract_signature_place'] ?? '');
    // Content is "ARTICLE n — Title\nBody" blocks separated by blank lines; anything else is kept as a paragraph.
    $blocks = collect(preg_split('/\n\s*\n/u', (string) ($document->content ?? '')))->filter(fn ($block) => trim($block) !== '')->map(function (string $block) {
        $lines = explode("\n", $block, 2);
        if (preg_match('/^ARTICLE\s+(\d+)\s+—\s+(.+)$/u', trim($lines[0]), $match)) {
            return ['number' => $match[1], 'title' => $match[2], 'body' => trim($lines[1] ?? '')];
        }

        return ['number' => null, 'title' => null, 'body' => trim($block)];
    })
        // The signature block below replaces the plain-text signatures article.
        ->reject(fn (array $block) => $signable && (in_array($block['title'], ['Signataires', 'Signatures'], true) || str_starts_with($block['body'], 'Date et signature')));

    // Presentation only: internal choice lists and codes are shown in plain French.
    $readable = function (string $label, string $value): array {
        $label = preg_replace('/\s*:\s*(?:[a-z_]+\s*\/\s*)+[a-z_]+\s*$/u', '', $label);
        // "cession / licence / autre : cession" keeps only the chosen value.
        $value = preg_replace('/^(?:[a-z_]+\s*\/\s*)+[a-z_]+\s*:\s*/u', '', trim($value));
        $value = ['codenyr' => 'Codenyr', 'client' => 'Le client', 'non_applicable' => 'Non applicable', 'indetermine' => 'À déterminer', 'cession' => 'Cession de droits', 'licence' => 'Licence', 'autre' => 'Autre disposition', 'oui' => 'Oui', 'non' => 'Non', 'aucune' => 'Aucune', 'personnalisee' => 'Personnalisée', 'essentiel' => 'Essentiel', 'pro' => 'Pro', 'business' => 'Business', 'accepted' => 'Accepté', 'sent' => 'Envoyé'][$value] ?? $value;

        return [trim($label), trim($value)];
    };
    // Splits a body into definition rows ("Label : value"), short sub-headings and paragraphs.
    $segments = function (string $body) use ($readable): array {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $body)), fn ($line) => $line !== ''));
        $out = [];
        $isDefinition = fn (string $line) => preg_match('/^(.{2,80}?)\s:\s(.+)$/u', $line, $match)
            && ! str_contains($match[1], ',') && str_word_count($match[1], 0, 'àâäéèêëîïôöùûüçÀÂÉÈÊÎÔÛÇ’\'()') <= 7 ? $match : null;
        foreach ($lines as $index => $line) {
            $next = $lines[$index + 1] ?? null;
            // A short title followed by a long clause paragraph (e.g. "Confidentialité : durée…").
            if ($next !== null && mb_strlen($line) <= 90 && ! preg_match('/[.;!?»]$/u', $line) && mb_strlen($next) >= 100 && ! $isDefinition($next)) {
                $out[] = ['kind' => 'sub', 'text' => $line];
            } elseif ($match = $isDefinition($line)) {
                [$label, $value] = $readable($match[1], $match[2]);
                $last = array_key_last($out);
                $last !== null && $out[$last]['kind'] === 'defs' ? $out[$last]['rows'][] = [$label, $value] : $out[] = ['kind' => 'defs', 'rows' => [[$label, $value]]];
            } else {
                $out[] = ['kind' => 'text', 'text' => $line];
            }
        }

        return $out;
    };
@endphp

<div class="footer"><table><tr>
    <td>{{ $document->name }} · {{ ($seller['legal_name'] ?? '') ?: ($seller['name'] ?? 'Codenyr') }}@if(filled($seller['registration'] ?? '')) · {{ $seller['registration'] }}@endif<br>Version {{ $snapshot['revision'] ?? 1 }} du {{ $document->document_date->format('d/m/Y') }}@if($document->pdf_sha256) · Empreinte {{ substr($document->pdf_sha256, 0, 12) }}@endif</td>
    <td style="text-align:right;width:90px">Page <span class="pagenum"></span></td>
</tr></table></div>

@if(in_array($document->type, ['terms','contract']) && !$document->finalized_at && !$document->original_id)<p class="draft">BROUILLON — DOCUMENT NON FINALISÉ</p>@endif

@if(\App\Support\TestMode::enabled())<p class="test-stamp">MODE TEST — document généré en local, sans valeur juridique ni comptable</p>@endif
<table class="head"><tr>
    <td class="brand-cell"><div class="brand-band">@if(!empty($snapshot['logo_data']))<img src="{{ $snapshot['logo_data'] }}" alt="Logo">@else<img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/codenyr.png'))) }}" alt="Codenyr">@endif</div></td>
    <td class="doc-id">
        <p class="doc-kind">{{ \App\Models\Document::TYPES[$document->type] ?? 'Document' }}</p>
        <h1 class="doc-title">{{ $document->name }}</h1>
        <p class="doc-meta">Date : <strong>{{ $document->document_date->format('d/m/Y') }}</strong> · Version {{ $snapshot['revision'] ?? 1 }}<br>Document n° {{ $document->id }}@if(filled($client['name'] ?? '')) · Client : <strong>{{ $client['name'] }}</strong>@endif</p>
    </td>
</tr></table>
<div class="rule"></div>

@if($signable && (filled($client['name'] ?? '') || filled($seller['legal_name'] ?? '')))
<table class="parties"><tr>
    <td><div class="party">
        <p class="label">Le prestataire</p>
        <p class="party-name">{{ ($seller['legal_name'] ?? '') ?: ($seller['name'] ?? '') }}</p>
        <p class="lines">{{ $seller['address'] ?? '' }}</p>
        <p class="muted">{{ $seller['registration'] ?? '' }}</p>
    </div></td>
    <td class="party-gap"></td>
    <td><div class="party">
        <p class="label">Le client</p>
        <p class="party-name">{{ $client['name'] ?? '' }}</p>
        <p class="lines">{{ $client['address'] ?? '' }}</p>
        @if(filled($client['registration'] ?? ''))<p class="muted">{{ $client['registration'] }}</p>@endif
    </div></td>
</tr></table>
@endif

@foreach($blocks as $block)
    <div class="article">
        @if($block['title'])<h3><span>Article {{ $block['number'] }}</span> — {{ $block['title'] }}</h3>@endif
        @foreach($segments($block['body']) as $segment)
            @if($segment['kind'] === 'defs')
                <table class="defs">@foreach($segment['rows'] as [$label, $value])<tr><td class="def-label">{{ $label }}</td><td>{{ $value }}</td></tr>@endforeach</table>
            @elseif($segment['kind'] === 'sub')
                <p class="sub">{{ $segment['text'] }}</p>
            @else
                <p>{{ $segment['text'] }}</p>
            @endif
        @endforeach
    </div>
@endforeach

@if($signable)
<div class="signatures">
    <h2 class="section">Signatures</h2>
    <p style="margin-bottom:10px">Fait en deux exemplaires{{ filled($place) ? ' à '.$place : '' }}, le ................................. Chaque partie appose sa signature précédée de la mention manuscrite « Lu et approuvé ».</p>
    <table><tr>
        <td class="sign">
            <p class="sign-role">Le prestataire</p>
            <p class="sign-name">{{ ($seller['signatory'] ?? '') ?: (($seller['legal_name'] ?? '') ?: 'Codenyr') }}</p>
            <p class="muted">{{ implode(' · ', array_filter([$seller['signatory_role'] ?? '', $seller['legal_name'] ?? ''], 'filled')) }}</p>
            <p class="sign-field">Date : <span>&nbsp;</span></p>
            <div class="sign-area"></div>
            <p class="sign-hint">« Lu et approuvé » et signature</p>
        </td>
        <td class="sign-gap"></td>
        <td class="sign">
            <p class="sign-role">Le client</p>
            <p class="sign-name">{{ ($client['signatory'] ?? '') ?: ($client['name'] ?? '') }}</p>
            <p class="muted">{{ implode(' · ', array_filter([$client['signatory_role'] ?? '', $client['name'] ?? ''], 'filled')) }}</p>
            <p class="sign-field">Date : <span>&nbsp;</span></p>
            <div class="sign-area"></div>
            <p class="sign-hint">« Lu et approuvé » et signature</p>
        </td>
    </tr></table>
</div>
@endif
</body></html>
