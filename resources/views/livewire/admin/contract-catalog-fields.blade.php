<details class="field full"><summary>Contenu contractuel et effets des prestations</summary>
<p>Les livrables ci-dessous font partie du devis. Retirez les éléments non vendus. Une entrée par ligne.</p>
<div class="form-grid">
@foreach(['object'=>'Objet de la prestation', 'deliverables'=>'Fonctionnalités et livrables inclus', 'exclusions'=>'Prestations exclues', 'clause'=>'Conditions particulières / maintenance'] as $key=>$label)
<div class="field full"><label for="{{ $prefix }}-{{ $key }}">{{ $label }}</label><textarea id="{{ $prefix }}-{{ $key }}" wire:model="{{ $binding }}.{{ $key }}" rows="3"></textarea></div>
@endforeach
<label class="commercial-checkbox"><input type="checkbox" wire:model="{{ $binding }}.personal_data"> Traitement de données personnelles prévu</label>
<div class="field"><label for="{{ $prefix }}-maintenance">Maintenance</label><select id="{{ $prefix }}-maintenance" wire:model="{{ $binding }}.maintenance_mode"><option value="">Pas d’effet sur la maintenance</option>@foreach(['aucune','essentiel','pro','business','personnalisee'] as $mode)<option value="{{ $mode }}">{{ ucfirst($mode) }}</option>@endforeach</select></div>
@foreach(['domain'=>'Domaine', 'hosting'=>'Hébergement'] as $key=>$label)
<div class="field"><label for="{{ $prefix }}-{{ $key }}">{{ $label }} : gestion, titulaire, paiement et renouvellement</label><select id="{{ $prefix }}-{{ $key }}" wire:model="{{ $binding }}.{{ $key }}_mode"><option value="">Selon le projet</option><option value="client">Client</option><option value="codenyr">Codenyr</option><option value="non_applicable">Non applicable</option></select></div>
@endforeach
<div class="field"><label for="{{ $prefix }}-revisions">Séries de modifications</label><input id="{{ $prefix }}-revisions" type="number" min="0" wire:model="{{ $binding }}.revision_rounds"></div>
<div class="field"><label for="{{ $prefix }}-delay">Délai indicatif (jours)</label><input id="{{ $prefix }}-delay" type="number" min="0" wire:model="{{ $binding }}.delivery_days"></div>
<div class="field"><label for="{{ $prefix }}-pages">Nombre maximal de pages (si convenu)</label><input id="{{ $prefix }}-pages" type="number" min="1" wire:model="{{ $binding }}.page_count"></div>
<div class="field"><label for="{{ $prefix }}-annex">Annexe fonctionnelle / cahier des charges : référence et version</label><input id="{{ $prefix }}-annex" wire:model="{{ $binding }}.annex"></div>
<details class="field full"><summary>Responsabilités spécifiques et propriété intellectuelle</summary><div class="form-grid">
@foreach(\App\Services\ContractCatalogRules::responsibilityFields() as $key)
<div class="field"><label for="{{ $prefix }}-{{ $key }}">{{ \App\Services\LegalDocumentRules::sections()['Domaine et hébergement'][$key] }}</label><select id="{{ $prefix }}-{{ $key }}" wire:model="{{ $binding }}.responsibilities.{{ $key }}"><option value="">Selon la gestion choisie</option><option value="client">Client</option><option value="codenyr">Codenyr</option><option value="non_applicable">Non applicable</option></select></div>
@endforeach
@foreach(\App\Services\LegalDocumentRules::sections()['Propriété intellectuelle'] as $key=>$label)
<div class="field"><label for="{{ $prefix }}-{{ $key }}">{{ $label }}</label><textarea id="{{ $prefix }}-{{ $key }}" wire:model="{{ $binding }}.legal.{{ $key }}" rows="2"></textarea></div>
@endforeach
</div></details>
</div></details>
