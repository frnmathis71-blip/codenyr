@if($form['type']==='contract' && !empty($legalForm['snapshot']['commercial_scope']))
@php($scope = $legalForm['snapshot']['commercial_scope'])
<section class="field full admin-panel"><h3>Résumé du contrat de prestation</h3>
<p>Offre : {{ $scope['offer'] ?? 'Prestations personnalisées du devis' }}</p>
<p>Options : {{ implode(', ', $scope['options']) ?: 'Aucune' }}</p>
<p>Fonctionnalités personnalisées : {{ implode(', ', $scope['custom']) ?: 'Aucune' }}</p>
<p>Maintenance : {{ $legalForm['fields']['maintenance_mode'] }} · Données personnelles : {{ $legalForm['fields']['privacy_processing'] }}</p>
<p>Domaine : {{ $legalForm['fields']['domain_mode'] }} · Hébergement : {{ $legalForm['fields']['hosting_mode'] }}</p>
@if(!empty($legalForm['snapshot']['quote']['id']))<a class="text-link" href="{{ route('admin.billing.edit',['kind'=>'quotes','id'=>$legalForm['snapshot']['quote']['id']]) }}">Consulter / modifier le devis</a>@endif
</section>
@endif
<div class="field full" id="legal-checklist">
    <h3>Document complété à {{ $legalForm['percent'] }} %</h3>
    <progress value="{{ $legalForm['percent'] }}" max="100" aria-label="Complétude du document"></progress>
    <p>Les contrôles configurés ne constituent pas une certification juridique. Vérifiez les valeurs préremplies avant de générer le PDF.</p>
    @if($legalForm['errors'])<ul>@foreach($legalForm['errors'] as $key=>$error)<li><a href="#{{ \App\Services\LegalDocumentRules::errorTarget($key) }}" x-on:click.prevent="focusDocumentField(@js(\App\Services\LegalDocumentRules::errorTarget($key)))">{{ $error }}</a></li>@endforeach</ul>@endif
</div>
<div class="form-grid" id="legal-sources">
<p class="field full">Complétez les informations ci-dessous puis cliquez sur « Remplir le document ». Les valeurs sont préremplies et les modifications sont conservées dans ce document.</p>
@foreach(\App\Services\LegalDocumentRules::formSources($form['type']) as $group => $definitions)
<details class="field full" open><summary>{{ ['seller'=>'Prestataire', 'client'=>'Client', 'project'=>'Projet et planning', 'quote'=>'Conditions du devis', 'terms'=>'Version et texte des CGV'][$group] }}</summary><div class="form-grid">
@foreach($definitions as $key => $label)
<div class="field" wire:key="source-{{ $form['type'] }}-{{ $group }}-{{ $key }}"><label for="legal-{{ $group }}_{{ $key }}">{{ $label }}</label>
@if($key === 'audience')
<select id="legal-client_audience" wire:model.live="contract.client_audience"><option value="">À choisir</option><option value="professional">Professionnel</option><option value="consumer">Consommateur / particulier</option></select>
@elseif(in_array($key, ['starts_on', 'due_on', 'effective_on']))
<input id="legal-{{ $group }}_{{ $key }}" type="date" wire:model.live="contract.{{ $group }}_{{ $key }}">
@elseif($group === 'terms' && $key === 'revision')
<input id="legal-terms_revision" type="number" min="1" step="1" inputmode="numeric" wire:model.live="contract.terms_revision" aria-describedby="terms-revision-help">
<small id="terms-revision-help">Nombre entier, par exemple 1 ou 2 (sans « v »).</small>
@else
<textarea id="legal-{{ $group }}_{{ $key }}" wire:model.live.debounce.500ms="contract.{{ $group }}_{{ $key }}" rows="2"></textarea>
@endif

</div>
@endforeach</div></details>
@endforeach
<div class="field full"><label for="legal-terms_template_id">Version des CGV applicable</label><select id="legal-terms_template_id" wire:model.live="contract.terms_template_id"><option value="">À choisir</option>@foreach($templates->where('type','terms') as $template)<option value="{{ $template->id }}">{{ $template->name }} v{{ $template->revision }} — {{ $template->effective_on ?: 'Date d’effet à renseigner' }}</option>@endforeach</select></div>
<details class="field full" open><summary>Clauses applicables</summary><div class="form-grid">
@foreach(\App\Services\LegalDocumentRules::CLAUSES as $key => $label)
@if((!str_ends_with($key, '_professional') || ($legalForm['snapshot']['audience'] ?? '') === 'professional') && (!str_ends_with($key, '_consumer') || ($legalForm['snapshot']['audience'] ?? '') === 'consumer'))
<div class="field" wire:key="clause-{{ $key }}"><label for="legal-clause_{{ $key }}">{{ $label }}</label><textarea id="legal-clause_{{ $key }}" wire:model.live.debounce.500ms="contract.clause_{{ $key }}" rows="4"></textarea></div>
@endif
@endforeach</div></details>
@foreach($legalForm['variableFields'] ?? [] as $key => $label)
<div class="field" wire:key="{{ $key }}"><label for="legal-{{ $key }}">{{ $label }}</label><textarea id="legal-{{ $key }}" wire:model.live.debounce.500ms="contract.{{ $key }}" rows="2"></textarea></div>
@endforeach
</div>
@if($form['type']==='terms')<div class="field full"><label for="legal-additional">Précisions contractuelles (facultatif)</label><textarea id="legal-additional" wire:model.live.debounce.500ms="contract.additional"></textarea></div>@endif
@if($form['type']==='contract')
@foreach(\App\Services\LegalDocumentRules::sections() as $section=>$fields)
<details class="field full" @if(count(array_intersect_key($legalForm['errors'],$fields))) open @endif><summary>{{ $section }}</summary><div class="form-grid">
@foreach($fields as $key=>$label)
@php($skip = !\App\Services\LegalDocumentRules::active($key, $legalForm['fields']))
@if(!$skip)<div class="field" wire:key="contract-field-{{ $key }}"><label for="legal-{{ $key }}">{{ $label }}</label>
@if(\App\Services\LegalDocumentRules::choices($key))<select id="legal-{{ $key }}" wire:model.live="contract.{{ $key }}"><option value="">À choisir</option>@foreach(\App\Services\LegalDocumentRules::choices($key) as $choice)<option>{{ $choice }}</option>@endforeach</select>@else<textarea id="legal-{{ $key }}" wire:model.live.debounce.500ms="contract.{{ $key }}" rows="2" placeholder="{{ $legalForm['fields'][$key] }}"></textarea>@endif
<small>Source : {{ $legalForm['provenance'][$key] }}@if($legalForm['provenance'][$key]==='Paramètres commerciaux') · {{ $legalForm['fields'][$key] ?: 'À renseigner' }}@endif</small>
</div>@endif
@endforeach</div></details>
@endforeach
@endif
<details class="field full"><summary>Aperçu actualisé du document</summary><p class="preserve-lines">{{ $legalForm['content'] }}</p></details>
