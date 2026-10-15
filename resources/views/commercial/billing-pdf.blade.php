<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">@include('commercial.pdf-style')</head><body>
@php
    $seller = $record->snapshot['seller'];
    $client = $record->snapshot['client'];
    $euros = fn ($cents) => \App\Services\CommercialCalculator::euros($cents);
    $isQuote = $kind === 'quote';
    $bank = config('codenyr.legal.bank');
@endphp

{{-- Footer on every page: legal identity and page number. --}}
<div class="footer"><table><tr>
    <td>{{ implode(' · ', array_filter([$seller['legal_name'] ?: $seller['name'], $seller['address'] ?? '', $seller['registration'] ?? ''], 'filled')) }}<br>{{ implode(' · ', array_filter([$seller['tax_mention'] ?? '', $seller['footer'] ?? ''], 'filled')) }}</td>
    <td style="text-align:right;width:90px">Page <span class="pagenum"></span></td>
</tr></table></div>

@unless($record->finalized_at)<p class="draft">BROUILLON — {{ $kind==='invoice' ? 'Facture non émise' : 'Devis non envoyé' }}</p>@endunless

@if(\App\Support\TestMode::enabled())<p class="test-stamp">MODE TEST — document généré en local, sans valeur juridique ni comptable</p>@endif
<table class="head"><tr>
    <td class="brand-cell"><div class="brand-band"><img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/codenyr.png'))) }}" alt="Codenyr"></div></td>
    <td class="doc-id">
        <p class="doc-kind">{{ $isQuote ? 'Devis' : ($record instanceof \App\Models\Invoice ? \App\Models\Invoice::TYPES[$record->invoice_type] : 'Facture') }}</p>
        <h1 class="doc-title">{{ $isQuote ? 'Devis' : 'Facture' }} {{ $record->number ?? 'Brouillon #'.$record->id }}</h1>
        <p class="doc-meta">Date : <strong>{{ $record->issued_on->format('d/m/Y') }}</strong><br>{{ $isQuote ? 'Valable jusqu’au' : 'Échéance' }} : <strong>{{ $record->due_on->format('d/m/Y') }}</strong><br>Projet : <strong>{{ $record->snapshot['project']['name'] }}</strong></p>
    </td>
</tr></table>
<div class="rule"></div>

<table class="parties"><tr>
    <td><div class="party">
        <p class="label">Émetteur</p>
        <p class="party-name">{{ $seller['legal_name'] ?: $seller['name'] }}</p>
        <p class="lines">{{ $seller['address'] }}</p>
        <p>{{ $seller['email'] }}@if($seller['phone']) · {{ $seller['phone'] }}@endif</p>
        <p class="muted">{{ $seller['registration'] }}</p>
    </div></td>
    <td class="party-gap"></td>
    <td><div class="party">
        <p class="label">{{ $isQuote ? 'Destinataire' : 'Facturé à' }}</p>
        <p class="party-name">{{ $client['name'] }}</p>
        @if(filled($client['contact'] ?? ''))<p>{{ $client['contact'] }}</p>@endif
        <p class="lines">{{ $client['address'] }}</p>
        <p>{{ $client['email'] }}@if(filled($client['phone'] ?? '')) · {{ $client['phone'] }}@endif</p>
        @if(filled($client['registration'] ?? ''))<p class="muted">{{ $client['registration'] }}</p>@endif
    </div></td>
</tr></table>

@foreach(\App\Models\Service::FREQUENCIES as $frequency=>$label)
@php($lines=array_filter($record->items,fn($item)=>($item['payee'] ?? 'codenyr') !== 'client' && $item['frequency']===$frequency && (!$item['optional'] || $item['selected'])))
@if(count($lines))
<h2 class="section">{{ $frequency==='once' ? ($kind==='invoice' ? 'Prestations facturées' : 'Prestations initiales') : 'Prestations récurrentes — '.$label }}</h2>
<table class="items"><thead><tr><th>Prestation</th><th>Qté</th><th class="num">PU HT</th><th class="num">Remise</th><th class="num">TVA</th></tr></thead><tbody>
@foreach($lines as $line)<tr>
<td><p class="item-name">{{ $line['name'] }}</p><div class="description lines">{{ $line['description'] }}</div>@if($kind==='quote')<div class="description lines">{{ implode("\n", $line['contract_rules']['deliverables'] ?? []) }}</div>@if(!empty($line['contract_rules']['page_count']))<div class="description">Jusqu’à {{ $line['contract_rules']['page_count'] }} pages.</div>@endif
@if(!empty($line['contract_rules']['annex']))<div class="description">Annexe fonctionnelle / Cahier des charges : {{ $line['contract_rules']['annex'] }}</div>@endif
@foreach($line['contract_rules']['fields'] ?? [] as $field=>$value)<div class="description">{{ collect(\App\Services\LegalDocumentRules::sections())->collapse()[$field] ?? $field }} : {{ $value }}</div>@endforeach
@foreach($line['contract_rules']['clauses'] ?? [] as $clause)<div class="description lines">{{ $clause }}</div>@endforeach @endif
@if(!empty($line['period_start']) && !empty($line['period_end']))<div class="description">Période facturée : du {{ \Carbon\Carbon::parse($line['period_start'])->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($line['period_end'])->format('d/m/Y') }}</div>@endif</td>
<td>{{ rtrim(rtrim(\App\Services\CommercialCalculator::input($line['quantity_units']), '0'), '.') }}@if($line['unit'] !== 'forfait') {{ $line['unit'] }}@endif</td>
<td class="num">{{ $euros($line['price_cents']) }}</td>
<td class="num">{{ $line['discount_type']==='percent' ? \App\Services\CommercialCalculator::input($line['discount_value']).' %' : $euros($line['discount_value']) }}</td>
<td class="num">{{ \App\Services\CommercialCalculator::input($line['vat_basis']) }} %</td>
</tr>@endforeach
</tbody></table>
@php($total=$frequency==='once' ? $record->totals['initial'] : $record->totals['recurring'][$frequency])
<table class="totals-wrap" style="margin-top:10px"><tr><td class="spacer"></td><td>
    <table class="totals">
        <tr><td>Sous-total HT</td><td class="amount">{{ $euros($total['subtotal']) }}</td></tr>
        @if($total['discount'])<tr><td>Remises</td><td class="amount">− {{ $euros($total['discount']) }}</td></tr>@endif
        <tr><td>Total HT</td><td class="amount">{{ $euros($total['ht']) }}</td></tr>
        <tr><td>TVA</td><td class="amount">{{ $euros($total['vat']) }}</td></tr>
        <tr class="grand"><td>{{ $frequency==='once' ? ($kind==='invoice' ? 'Total TTC à régler' : 'Total initial TTC') : 'Total TTC / '.$label }}</td><td class="amount">{{ $euros($total['ttc']) }}</td></tr>
    </table>
</td></tr></table>
@endif
@endforeach

@if($kind==='quote' && ($record->totals['deposit'] ?? 0) > 0)
<div class="box"><p class="box-title">Échéancier</p><p>Acompte à la commande : <strong>{{ $euros($record->totals['deposit']) }}</strong> · Solde prévisionnel à la livraison : <strong>{{ $euros($record->totals['remaining']) }}</strong></p></div>
@endif

@php($options=array_filter($record->items,fn($item)=>$item['optional'] && !$item['selected']))
@if($record instanceof \App\Models\Quote)
@php($commercialScope = app(\App\Services\ContractScopeBuilder::class)->build($record))
@if($commercialScope['excluded'])<h2 class="section">Prestations non comprises</h2><p class="lines">{{ implode("\n", $commercialScope['excluded']) }}</p>@endif
@endif
@if(count($options))<h2 class="section">Options non retenues — hors total</h2>@foreach($options as $option)<p>{{ $option['name'] }} · {{ $euros($option['price_cents']) }} HT / {{ $option['unit'] }} · {{ \App\Models\Service::FREQUENCIES[$option['frequency']] }}</p>@endforeach @endif
@php($external=array_filter($record->items,fn($item)=>($item['payee'] ?? 'codenyr') === 'client' && (!$item['optional'] || $item['selected'])))
@if(count($external))<h2 class="section">Frais tiers — payés directement par le client au prestataire, hors total Codenyr</h2>@foreach($external as $line)<p><strong>{{ $line['name'] }}</strong> · {{ \App\Services\CommercialCalculator::input($line['quantity_units']) }} × {{ $euros($line['price_cents']) }} HT / {{ $line['unit'] }} · {{ \App\Models\Service::FREQUENCIES[$line['frequency']] }}<br>{{ $line['description'] }}</p>@endforeach @endif

<h2 class="section">Conditions</h2>
<p class="lines">{{ $record->conditions }}</p>
@if($record->estimated_delay)<p style="margin-top:6px">Délai estimé : <strong>{{ $record->estimated_delay }}</strong></p>@endif
<p class="lines muted" style="margin-top:6px">{{ $seller['external_fees_mention'] ?? '' }}</p>
<p class="lines" style="margin-top:6px"><strong>{{ $seller['tax_mention'] }}</strong></p>

@if($kind==='invoice' && filled($bank['iban'] ?? null))
<div class="box"><p class="box-title">Règlement par virement</p><p>Titulaire : {{ $bank['holder'] }} · IBAN : <strong>{{ $bank['iban'] }}</strong>@if(filled($bank['bic'] ?? null)) · BIC : {{ $bank['bic'] }}@endif<br>Merci d’indiquer la référence <strong>{{ $record->number }}</strong> dans le libellé du virement.</p></div>
@endif

@if($kind==='quote')
<div class="signatures">
    <h2 class="section">Bon pour accord</h2>
    @if(($record->totals['deposit'] ?? 0) > 0)<p style="margin-bottom:10px">En signant ce devis, le client accepte de verser l’acompte de <strong>{{ $euros($record->totals['deposit']) }}</strong> avant le démarrage du projet. Le projet commencera après réception de cet acompte.</p>@endif
    <table><tr>
        <td class="sign">
            <p class="sign-role">Le prestataire</p>
            <p class="sign-name">{{ $seller['signatory'] ?? ($seller['legal_name'] ?: $seller['name']) }}</p>
            <p class="muted">{{ $seller['signatory_role'] ?? '' }}</p>
            <p class="sign-field">Date : {{ $record->issued_on->format('d/m/Y') }}</p>
            <div class="sign-area"></div>
            <p class="sign-hint">Signature</p>
        </td>
        <td class="sign-gap"></td>
        <td class="sign">
            <p class="sign-role">Le client</p>
            <p class="sign-name">{{ $client['contact'] ?? '' ?: $client['name'] }}</p>
            <p class="muted">{{ $client['name'] }}</p>
            <p class="sign-field">Date : <span>&nbsp;</span></p>
            <div class="sign-area"></div>
            <p class="sign-hint">Signature précédée de la mention « Bon pour accord »</p>
        </td>
    </tr></table>
</div>
@endif
</body></html>
