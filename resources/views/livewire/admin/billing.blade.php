<div><p class="eyebrow">Codenyr / Gestion commerciale</p><div class="admin-toolbar"><h1>{{ $kind==='quotes' ? 'Devis' : 'Factures' }}{{ $record ? ' · '.($record->number ?? 'Brouillon #'.$record->id) : '' }}</h1>@unless($editor)<a class="button button-primary" href="{{ route('admin.billing.'.$kind.'.create') }}">+ {{ $kind==='quotes' ? 'Nouveau devis' : 'Nouvelle facture' }}</a>@else<a class="text-link" href="{{ route('admin.billing.'.$kind.'.index') }}">Tous les {{ $kind==='quotes' ? 'devis' : 'factures' }} →</a>@endunless</div>
@include('livewire.admin.commercial-errors')
@unless($editor)
<div class="admin-toolbar"><input placeholder="Numéro, client ou projet…" aria-label="Rechercher" wire:model.live.debounce.300ms="search"><select aria-label="Statut" wire:model.live="filter"><option value="">Tous les documents actifs</option>@foreach($kind==='quotes' ? \App\Models\Quote::STATUSES : ['draft'=>'Brouillon','issued'=>'Émise'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach<option value="archived">Archivés</option></select></div>
<div class="admin-panel table-wrap"><table class="admin-table"><thead><tr><th>Numéro</th><th>Client / projet</th><th>Total initial TTC</th><th>Statut</th><th></th></tr></thead><tbody>@forelse($records as $item)<tr><td>{{ $item->number ?? 'Brouillon #'.$item->id }}</td><td>{{ $item->project->client->name }}<small>{{ $item->project->name }}</small></td><td>{{ \App\Services\CommercialCalculator::euros($item->totals['initial']['ttc']) }}</td><td>{{ $item->archived_at ? 'Archivé' : ($kind==='quotes' ? \App\Models\Quote::STATUSES[$item->status] : $item->paymentLabel()) }}</td><td><a class="text-link" href="{{ route('admin.billing.'.$kind.'.edit',['id'=>$item->id]) }}">Consulter →</a></td></tr>@empty<tr><td colspan="5">Aucun document.</td></tr>@endforelse</tbody></table></div>{{ $records->links() }}
@else
@include('livewire.admin.billing-actions')
@if($record instanceof \App\Models\Quote && $record->status==='accepted' && !$record->archived_at && !$record->project->archived_at)
<a class="button button-primary" href="{{ route('admin.documents', ['project'=>$record->client_project_id, 'quote'=>$record->id, 'create'=>1, 'type'=>'contract', 'mode'=>'generate']) }}">Créer le contrat</a>
@endif
@if($record instanceof \App\Models\Invoice)
<section class="admin-panel"><h2>État et règlements de la facture</h2>
<p><strong>{{ $record->paymentLabel() }}</strong> · Reçu : {{ \App\Services\CommercialCalculator::euros($record->paidCents()) }} · Reste dû : {{ \App\Services\CommercialCalculator::euros($record->remainingCents()) }}</p>
<p>L’état passe à « Partiellement payée » ou « Payée » selon les règlements saisis. Enregistrez uniquement les sommes réellement reçues.</p>
@if($record->finalized_at && !$record->archived_at && !$record->project->archived_at)
<form wire:submit="pay"><div class="form-grid">
<div class="field"><label for="invoice-payment-amount">Montant reçu (€)</label><input id="invoice-payment-amount" wire:model="payment.amount" inputmode="decimal" required></div>
<div class="field"><label for="invoice-payment-date">Date de réception</label><input id="invoice-payment-date" wire:model="payment.paid_on" type="date" required></div>
<div class="field"><label for="invoice-payment-method">Mode de paiement</label><select id="invoice-payment-method" wire:model="payment.method">
@foreach(\App\Models\Payment::METHODS as $key=>$label)
<option value="{{ $key }}">{{ $label }}</option>
@endforeach
</select></div>
<div class="field"><label for="invoice-payment-reference">Référence</label><input id="invoice-payment-reference" wire:model="payment.reference"></div></div>
<div class="button-row"><button type="button" class="button button-secondary" wire:click="fillBalance">Renseigner le solde</button><button class="button button-primary" wire:loading.attr="disabled">Enregistrer le règlement</button></div></form>
<div class="field"><label for="payment-correction">Motif en cas de saisie erronée</label><input id="payment-correction" wire:model="correctionReason" placeholder="Exemple : règlement saisi deux fois"></div>
@endif
@foreach($record->payments as $entry)
<p>{{ $entry->paid_on->format('d/m/Y') }} · {{ \App\Services\CommercialCalculator::euros($entry->amount_cents) }} · {{ \App\Models\Payment::METHODS[$entry->method] }}
@if($entry->voided_at)
<strong>Saisie annulée</strong> — {{ $entry->void_reason }}
@elseif(!$record->archived_at && !$record->project->archived_at)
<button class="text-link" wire:click="voidPayment({{ $entry->id }})" wire:confirm="Annuler cette saisie de règlement ? Cette action corrige le suivi et conserve l’historique ; elle ne rembourse pas le client.">Corriger cette saisie</button>
@endif
</p>
@endforeach
@unless($record->finalized_at)<p>Utilisez « Émettre la facture » après avoir complété le brouillon pour pouvoir enregistrer un règlement.</p>@endunless
</section>
@endif
<div class="commercial-editor"><section>
<form wire:submit="save"><section class="admin-panel"><h2 class="billing-step"><span>1</span> Client et projet</h2>@unless($readOnly || $derived)<p>Les taux de TVA saisis sur les lignes sont appliqués à ce document. Les paramètres commerciaux définissent seulement les taux par défaut ; saisissez 0 pour une ligne sans TVA.</p>@endunless<fieldset @disabled($readOnly)><div class="form-grid"><div class="field full"><label for="billing-project">Projet et client</label><select id="billing-project" wire:model.live="form.client_project_id" @disabled($record)><option value="">Choisir un projet</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }} — {{ $project->client->name }}</option>@endforeach
@if($record && $record->project->archived_at)<option value="{{ $record->client_project_id }}">{{ $record->project->name }} (archivé)</option>@endif</select>@unless($record)<small>Le client n’a pas encore de projet ? <a class="text-link" href="{{ route('admin.client-projects.index') }}">Créer un projet</a>, puis revenez ici.</small>@endunless</div>@foreach(['issued_on'=>'Date','due_on'=>$kind==='quotes' ? 'Validité' : 'Échéance'] as $key=>$label)<div class="field"><label for="billing-{{ $key }}">{{ $label }}</label><input id="billing-{{ $key }}" type="date" wire:model="form.{{ $key }}"></div>@endforeach
@if($kind==='invoices')<div class="field"><label for="billing-type">Type de facture</label><select id="billing-type" wire:model="form.invoice_type" @disabled($derived)>@foreach(\App\Models\Invoice::TYPES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>@endif</div></fieldset></section>
@unless($readOnly || $derived)<section class="admin-panel"><h2 class="billing-step"><span>2</span> {{ $kind==='quotes' ? 'Offre et prestations' : 'Prestations à facturer' }}</h2>@if($kind==='invoices')<p>Pour facturer uniquement un abonnement, sélectionnez sa prestation et indiquez la période facturée. Créez une nouvelle facture pour chaque période.</p>@endif
@if($kind==='quotes')<div class="field"><label for="main-offer">Offre principale</label><select id="main-offer" wire:model.live="offerChoice"><option value="">Choisir une offre</option>@foreach($offers as $offer)<option value="{{ $offer->price_key }}">{{ $offer->name }} — {{ $offer->priceLabel() }}</option>@endforeach</select></div><label class="commercial-checkbox"><input type="checkbox" wire:model.live="showAllServices">Afficher aussi les prestations hors compatibilité (ajout manuel)</label><p>Les quantités des suppléments correspondent uniquement au dépassement de l’offre. Site Pro inclut l’administration et 2 CRUD. Maintenance ≠ modification ≠ nouvelle fonctionnalité.</p>@endif
@foreach($services->groupBy('category') as $category=>$group)<h3>{{ $category }}</h3><div class="commercial-catalog">@foreach($group as $service)<label class="commercial-checkbox" wire:key="catalog-{{ $service->id }}"><input type="checkbox" @checked(in_array($service->id,$selectedServices)) wire:click="toggleService({{ $service->id }})"><span>{{ $service->name }}<small>{{ $service->priceLabel() }} / {{ $service->unit }} · {{ \App\Models\Service::FREQUENCIES[$service->frequency] }}</small></span></label>@endforeach</div>@endforeach<a class="text-link" href="{{ route('admin.catalog') }}">Administrer le catalogue →</a></section>@endunless
@if($derived)<p>Les montants sont calculés depuis le devis accepté et les factures déjà émises. Le contenu financier de ce brouillon est conservé.</p>@endif
@if(count($items))<h2 class="billing-step"><span>3</span> Lignes {{ $kind==='quotes' ? 'du devis' : 'de la facture' }}</h2>@endif
@foreach($items as $index=>$line)
@php($lineTotal = $line['frequency'] === 'once' || $kind === 'invoices' ? ($totals['initial']['rows'][$index]['net'] ?? null) : ($totals['recurring'][$line['frequency']]['rows'][$index]['net'] ?? null))
<section class="admin-panel billing-line" wire:key="billing-line-{{ $index }}">
<div class="billing-line-head"><h3>{{ $line['name'] ?: 'Prestation '.($index+1) }}</h3>@if($lineTotal !== null)<strong>{{ \App\Services\CommercialCalculator::euros($lineTotal) }} HT</strong>@endif@unless($readOnly || $derived)<button type="button" class="text-link" wire:click="removeItem({{ $index }})">Retirer</button>@endunless</div>
<fieldset @disabled($readOnly || $derived)>
{{-- Essentials: what the client reads on the document. --}}
<div class="billing-line-main">
<div class="field billing-line-name"><label for="line-{{ $index }}-name">Nom</label><input id="line-{{ $index }}-name" wire:model.live.debounce.400ms="items.{{ $index }}.name"></div>
@foreach(['quantity'=>'Quantité','price'=>'Prix unitaire HT (€)','vat'=>'TVA (%)'] as $key=>$label)<div class="field"><label for="line-{{ $index }}-{{ $key }}">{{ $label }}</label><input id="line-{{ $index }}-{{ $key }}" inputmode="decimal" wire:model.live.debounce.400ms="items.{{ $index }}.{{ $key }}"></div>@endforeach
<div class="field full"><label for="line-{{ $index }}-description">Description</label><textarea id="line-{{ $index }}-description" wire:model.live.debounce.400ms="items.{{ $index }}.description" rows="2"></textarea></div>
@if($kind==='invoices' && $line['frequency']!=='once')<div class="field"><label for="line-{{ $index }}-period-start">Période facturée : du</label><input id="line-{{ $index }}-period-start" type="date" wire:model.live="items.{{ $index }}.period_start"></div><div class="field"><label for="line-{{ $index }}-period-end">Au</label><input id="line-{{ $index }}-period-end" type="date" wire:model.live="items.{{ $index }}.period_end"></div><p class="field full billing-hint">Le prix et la quantité couvrent la période indiquée, sans prorata automatique. Le montant est inclus dans le total de cette facture.</p>@endif
@if($kind==='quotes' && $line['optional'])<label class="commercial-checkbox"><input type="checkbox" wire:model.live="items.{{ $index }}.selected"> Option retenue par le client</label>@endif
</div>
{{-- Everything else stays available, folded away; wire:ignore.self keeps it open across re-renders. --}}
<details class="billing-more" wire:ignore.self><summary>Plus d’options <small>remise, unité, fréquence, règlement{{ $kind==='quotes' ? ', contrat' : '' }}</small></summary>
<div class="form-grid">
@foreach(['category'=>'Catégorie','unit'=>'Unité','discount'=>'Remise'] as $key=>$label)<div class="field"><label for="line-{{ $index }}-{{ $key }}">{{ $label }}</label><input id="line-{{ $index }}-{{ $key }}" wire:model.live.debounce.400ms="items.{{ $index }}.{{ $key }}"></div>@endforeach
<div class="field"><label for="line-{{ $index }}-discount-type">Type de remise</label><select id="line-{{ $index }}-discount-type" wire:model.live="items.{{ $index }}.discount_type"><option value="percent">Pourcentage</option><option value="fixed">Montant (€)</option></select></div>
<div class="field"><label for="line-{{ $index }}-frequency">Fréquence</label><select id="line-{{ $index }}-frequency" wire:model.live="items.{{ $index }}.frequency">@foreach(\App\Models\Service::FREQUENCIES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="line-{{ $index }}-payee">Règlement</label><select id="line-{{ $index }}-payee" wire:model.live="items.{{ $index }}.payee">@foreach(\App\Models\Service::PAYEES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><small>Les frais payés directement au prestataire sont exclus des totaux Codenyr.</small></div>
<p class="field full billing-hint">{{ \App\Models\Service::PRICING_MODES[$line['pricing_mode'] ?? 'fixed'] }} · Prix propre à ce document, modifiable sans changer le catalogue. @if(($line['pricing_mode'] ?? '') === 'quote')Renseignez un montant avant l’enregistrement.@endif</p>
@if($kind==='quotes')
@include('livewire.admin.contract-catalog-fields', ['prefix'=>'line-contract-'.$index, 'binding'=>'ruleEditors.'.$index])
<label class="commercial-checkbox"><input type="checkbox" wire:model="items.{{ $index }}.contract_included"> Incluse au contrat</label>
<label class="commercial-checkbox"><input type="checkbox" wire:model.live="items.{{ $index }}.optional"> Option facultative</label>@unless($line['optional'])<label class="commercial-checkbox"><input type="checkbox" wire:model.live="items.{{ $index }}.selected"> Option retenue</label>@endunless
@endif
</div>
</details>
</fieldset>
</section>
@endforeach
@unless($readOnly || $derived)<button type="button" class="button button-secondary" wire:click="addItem">+ Ajouter une ligne libre</button>@endunless
<section class="admin-panel"><h2 class="billing-step"><span>4</span> Conditions et {{ $kind==='quotes' ? 'envoi' : 'émission' }}</h2><fieldset @disabled($readOnly)><div class="form-grid">@unless($derived)<div class="field"><label for="global-discount">Remise globale</label><input id="global-discount" wire:model.live.debounce.400ms="form.discount"></div><div class="field"><label for="global-discount-type">Type de remise</label><select id="global-discount-type" wire:model.live="form.discount_type"><option value="percent">Pourcentage</option><option value="fixed">Montant (€) sur le total initial</option></select></div>@if($kind==='quotes')<div class="field"><label for="deposit-type">Acompte</label><select id="deposit-type" wire:model.live="form.deposit_type"><option value="none">Aucun</option><option value="percent">Pourcentage</option><option value="fixed">Montant fixe (€)</option></select></div><div class="field"><label for="deposit-value">Valeur de l’acompte</label><input id="deposit-value" wire:model.live.debounce.400ms="form.deposit"></div>@endif
@endunless<div class="field full"><label for="billing-conditions">Conditions / mentions</label><textarea id="billing-conditions" wire:model="form.conditions" rows="4"></textarea></div><div class="field full"><label for="billing-delay">Délai estimé</label><input id="billing-delay" wire:model="form.estimated_delay"></div></div></fieldset>@unless($readOnly)<div class="button-row"><button class="button button-primary" wire:loading.attr="disabled">Enregistrer le brouillon</button><button type="button" class="button button-secondary" wire:click="finalize" wire:loading.attr="disabled" wire:confirm="{{ $kind==='quotes' ? 'Conserver ce devis et le marquer envoyé ? Vous pourrez le dupliquer pour une nouvelle version.' : 'Émettre définitivement cette facture ? Son contenu sera immuable.' }}">{{ $kind==='quotes' ? 'Marquer envoyé' : 'Émettre la facture' }}</button></div>@endunless</section></form>
@if($record && $kind==='quotes' && !$record->archived_at && !$record->project->archived_at)<section class="admin-panel"><h2>Suivi du devis</h2>@unless($record->status==='accepted')<p>{{ $record->finalized_at ? 'Quelle a été la réponse du client ?' : 'Où en est ce brouillon ?' }}</p><div class="button-row billing-status">@foreach($record->finalized_at ? ['accepted'=>'Accepté','refused'=>'Refusé','expired'=>'Expiré','cancelled'=>'Annulé'] : ['ready'=>'Prêt','draft'=>'Brouillon','cancelled'=>'Annulé'] as $key=>$label)<button type="button" @class(['button', 'button-primary' => $key === 'accepted', 'button-secondary' => $key !== 'accepted', 'is-current' => $record->status === $key]) wire:click="setStatus('{{ $key }}')" wire:loading.attr="disabled" @if($record->status === $key) aria-pressed="true" @endif>{{ $label }}</button>@endforeach</div>@else<p>Devis accepté. Étape suivante : facturer.</p><div class="admin-toolbar"><select aria-label="Type de facture" wire:model.live="invoiceType">@foreach(\App\Models\Invoice::TYPES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>@if($invoiceType === 'interim')<input aria-label="Montant TTC intermédiaire en euros" placeholder="Montant TTC (€)" wire:model="interimAmount">@endif<button class="button button-primary" wire:click="convert">Créer la facture depuis le devis</button></div>@endunless</section>@endif
</section><aside class="admin-panel commercial-preview"><p class="eyebrow">Aperçu des montants</p><h2>{{ $previewSeller['name'] }}</h2>@if($record)<p>{{ $record->snapshot['client']['name'] }}<br>{{ $record->snapshot['project']['name'] }}</p>@elseif($previewProject)<p>{{ $previewProject->client->name }}<br>{{ $previewProject->name }}</p>@endif
@if($previewError)<p>{{ $previewError }}</p>@elseif($totals)<dl class="commercial-totals">@foreach(['subtotal'=>'Sous-total HT','discount'=>'Remises','ht'=>'Total HT','vat'=>'TVA','ttc'=>$kind==='quotes' ? 'Total initial TTC' : 'Total TTC à régler'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ \App\Services\CommercialCalculator::euros($totals['initial'][$key]) }}</dd></div>@endforeach
@if($kind==='quotes')<div><dt>Acompte initial</dt><dd>{{ \App\Services\CommercialCalculator::euros($totals['deposit']) }}</dd></div><div><dt>Solde prévisionnel</dt><dd>{{ \App\Services\CommercialCalculator::euros($totals['remaining']) }}</dd></div>@endif</dl>@foreach($totals['recurring'] as $frequency=>$total)@if($total['ttc'])<div class="commercial-recurring"><strong>{{ \App\Services\CommercialCalculator::euros($total['ttc']) }}</strong><span> / {{ \App\Models\Service::FREQUENCIES[$frequency] }} TTC</span></div>@endif
@endforeach
@foreach($items as $external)@if(($external['payee'] ?? 'codenyr') === 'client' && (!$external['optional'] || $external['selected']))<p><strong>Frais tiers hors total :</strong> {{ $external['name'] }} — {{ $external['quantity'] }} × {{ $external['price'] }} € HT / {{ \App\Models\Service::FREQUENCIES[$external['frequency']] }}. Payé directement au prestataire.</p>@endif@endforeach
<p>{{ $previewSeller['external_fees_mention'] ?? '' }}</p>
@if($kind==='quotes')<small>Les récurrences sont séparées du paiement initial. Les options non retenues sont exclues.</small>@else<small>Les abonnements inclus sont facturés uniquement pour les périodes indiquées.</small>@endif
@endif</aside></div>
@include('livewire.admin.billing-actions', ['bottom' => true])
@endunless</div>


