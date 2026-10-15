{{-- Project autopilot: where the project stands and the one action to do next. --}}
@php
    $euros = fn (int $cents) => \App\Services\CommercialCalculator::euros($cents);
    $sentQuote = $project->quotes->where('status', 'sent')->whereNull('archived_at')->sortByDesc('id')->first();
    $draftQuote = $project->quotes->whereNull('finalized_at')->whereNull('archived_at')->sortByDesc('id')->first();
    $finalInvoice = $project->invoices->first(fn ($invoice) => in_array($invoice->invoice_type, ['standard', 'balance'], true) && $invoice->finalized_at);
    $stepNames = \App\Services\ProjectAutopilot::STEPS;
    $doneCount = count(array_filter($steps));
@endphp
<section class="admin-panel pilot" aria-labelledby="pilot-title">
    <div class="pilot-head">
        <h2 id="pilot-title">Pilote du dossier</h2>
        <span>{{ $doneCount }} / {{ count($steps) }} étapes</span>
    </div>
    <ol class="pilot-steps">
        @foreach($steps as $key => $done)
            <li @class(['is-done' => $done, 'is-current' => $key === $step])><span aria-hidden="true">{{ $done ? '✓' : $loop->iteration }}</span>{{ $stepNames[$key] }}@if($key === $step)<b class="sr-only"> (étape en cours)</b>@endif</li>
        @endforeach
    </ol>

    <div class="pilot-next">
        @php($view = $project->archived_at ? 'archived' : ($step ?? 'complete'))
        @if($view === 'archived')
            <p class="pilot-title">Dossier archivé</p>
            <p>Restaurez-le pour reprendre le suivi.</p>
        @endif
        @if($view === 'quote')
            <p class="pilot-title">Préparer le devis</p>
            <p>Choisissez l’offre : prestations, prix, acompte et conditions sont pré-remplis. Il ne reste qu’à ajuster.</p>
            <div class="button-row"><a class="button button-primary" href="{{ route('admin.billing.quotes.create', ['project' => $project->id]) }}">Préparer le devis</a></div>
        @endif
        @if($view === 'sent')
            <p class="pilot-title">Envoyer le devis</p>
            <p>Relisez le devis puis cliquez sur « Marquer envoyé » : le numéro définitif et le PDF sont créés automatiquement. Envoyez ensuite le PDF au client.</p>
            @if($draftQuote)<div class="button-row"><a class="button button-primary" href="{{ route('admin.billing.quotes.edit', ['id' => $draftQuote->id]) }}">Ouvrir le devis à envoyer</a></div>@endif
        @endif
        @if($view === 'accepted')
            <p class="pilot-title">Réponse du client</p>
            @if($sentQuote)
                <p>Devis {{ $sentQuote->number }} · {{ $euros($sentQuote->totals['initial']['ttc']) }} TTC, envoyé le {{ $sentQuote->sent_at?->format('d/m/Y') }}. Dès que le client répond, indiquez-le ici.</p>
                <div class="button-row">
                    <button type="button" class="button button-primary" wire:click="answerQuote({{ $sentQuote->id }}, 'accepted')" wire:loading.attr="disabled">Le client a accepté</button>
                    <button type="button" class="button button-secondary" wire:click="answerQuote({{ $sentQuote->id }}, 'refused')" wire:confirm="Marquer ce devis comme refusé ?">Il a refusé</button>
                </div>
            @else
                <p>Aucun devis envoyé en attente de réponse.</p>
            @endif
        @endif
        @if($view === 'kit')
            <p class="pilot-title">Préparer les documents de démarrage</p>
            @if(! in_array($project->client->audience, ['professional', 'consumer'], true) && ! \App\Support\TestMode::enabled())
                <p>Une seule question avant de tout générer : ce client est…</p>
                <div class="button-row">
                    <button type="button" class="button button-secondary" wire:click="setAudience('professional')">Un professionnel</button>
                    <button type="button" class="button button-secondary" wire:click="setAudience('consumer')">Un particulier</button>
                </div>
                <small>Cela choisit les bonnes clauses : pénalités de retard pour un professionnel, droit de rétractation et médiation pour un particulier.</small>
            @else
                <p>En un clic : CGV et contrat générés en PDF à partir du devis {{ $acceptedQuote?->number }}@if(($acceptedQuote?->totals['deposit'] ?? 0) > 0), et facture d’acompte de {{ $euros($acceptedQuote->totals['deposit']) }} émise.@else.@endif Les dates de début et de livraison sont planifiées si elles manquent.</p>
                @if(\App\Support\TestMode::enabled())<p class="pilot-warning">Mode test local : les documents sont émis même s’il manque des informations, et chaque PDF porte la mention « MODE TEST — sans valeur ».</p>@elseif(blank(config('codenyr.legal.siret')))<p class="pilot-warning">SIRET pas encore renseigné : les documents mentionneront « immatriculation en cours ». Attendez votre SIRET avant de les faire signer.</p>@endif
                <div class="button-row"><button type="button" class="button button-primary" wire:click="startKit" wire:loading.attr="disabled">Tout préparer</button></div>
                <small>Client {{ match($project->client->audience) { 'professional' => 'professionnel', 'consumer' => 'particulier', default => 'professionnel (par défaut en mode test)' } }} · <button type="button" class="text-link" wire:click="setAudience('{{ $project->client->audience === 'professional' ? 'consumer' : 'professional' }}')">changer</button></small>
            @endif
            @if($kitMissing)
                <div class="form-alert"><strong>Il manque encore :</strong><ul>@foreach($kitMissing as $item)<li>{{ $item }}</li>@endforeach</ul><a class="text-link" href="{{ route('admin.documents', ['project' => $project->id]) }}">Compléter dans Documents</a></div>
            @endif
        @endif
        @if($view === 'signed')
            <p class="pilot-title">Faire signer le client</p>
            <p>Envoyez ces PDF au client. Il les renvoie signés avec la mention « Bon pour accord » (un accord écrit par e-mail fonctionne aussi : enregistrez-le en PDF). Importez ensuite chaque version signée.</p>
            <ul class="pilot-docs">
                @foreach($legal as $type => $document)
                    @if($document)
                        <li><span>{{ $document->name }}</span>@if($document->signedCopies->isNotEmpty())<b>Signé ✓</b>@else<a class="text-link" href="{{ route('admin.documents.download', $document) }}">Télécharger</a><a class="button button-secondary" href="{{ route('admin.documents', ['project' => $project->id, 'sign' => $document->id]) }}">Importer la version signée</a>@endif</li>
                    @endif
                @endforeach
            </ul>
        @endif
        @if($view === 'deposit')
            <p class="pilot-title">Encaisser l’acompte</p>
            @if($deposit && ! $deposit->finalized_at)
                <p>La facture d’acompte est prête en brouillon.</p>
                <div class="button-row"><button type="button" class="button button-primary" wire:click="issueInvoice({{ $deposit->id }})" wire:loading.attr="disabled">Émettre la facture d’acompte</button></div>
            @elseif($deposit)
                <p>Facture {{ $deposit->number }} · reste {{ $euros($deposit->remainingCents()) }}. Quand le virement arrive, un clic l’enregistre à la date du jour : il apparaît aussitôt dans la comptabilité.</p>
                <div class="button-row"><button type="button" class="button button-primary" wire:click="markPaid({{ $deposit->id }})" wire:confirm="Enregistrer un virement de {{ $euros($deposit->remainingCents()) }} reçu aujourd’hui ?">Acompte reçu</button><a class="button button-secondary" href="{{ route('admin.billing.invoices.edit', ['id' => $deposit->id]) }}">Autre montant ou moyen</a></div>
            @endif
        @endif
        @if($view === 'delivered')
            <p class="pilot-title">Réaliser et livrer le site</p>
            <p>Livraison prévue le {{ $project->due_on?->format('d/m/Y') ?? 'à définir' }}. Quand le site est livré, indiquez-le : le client pourra aussi déposer un avis depuis son espace.</p>
            <div class="button-row"><button type="button" class="button button-primary" wire:click="markDelivered" wire:confirm="Confirmer la livraison du site ?">Le site est livré</button></div>
        @endif
        @if($view === 'final')
            <p class="pilot-title">Facturer le solde</p>
            <p>La facture reprend le devis accepté et déduit automatiquement ce qui a déjà été facturé.</p>
            <div class="button-row"><button type="button" class="button button-primary" wire:click="finalInvoice" wire:confirm="Émettre la facture de solde ? Son numéro et son contenu seront définitifs." wire:loading.attr="disabled">Émettre la facture de solde</button></div>
        @endif
        @if($view === 'paid' && $finalInvoice)
            <p class="pilot-title">Encaisser le solde</p>
            <p>Facture {{ $finalInvoice->number }} · reste {{ $euros($finalInvoice->remainingCents()) }}, échéance le {{ $finalInvoice->due_on->format('d/m/Y') }}.</p>
            <div class="button-row"><button type="button" class="button button-primary" wire:click="markPaid({{ $finalInvoice->id }})" wire:confirm="Enregistrer un virement de {{ $euros($finalInvoice->remainingCents()) }} reçu aujourd’hui ?">Solde reçu</button><a class="button button-secondary" href="{{ route('admin.billing.invoices.edit', ['id' => $finalInvoice->id]) }}">Autre montant ou moyen</a></div>
        @endif
        @if($view === 'complete')
            <p class="pilot-title">Dossier complet</p>
            <p>Devis, documents, factures et paiements sont à jour. Vous pouvez proposer une maintenance ou archiver le dossier.</p>
            <div class="button-row"><button type="button" class="button button-secondary" wire:click="$set('tab','maintenance')">Proposer une maintenance</button></div>
        @endif
    </div>
</section>
