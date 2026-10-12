<section class="admin-panel">
    <div class="admin-toolbar"><h2>{{ !empty($form['original_id']) ? 'Importer une version signée' : 'Préparer un document' }}</h2><button type="button" class="button button-secondary" wire:click="$set('showForm',false)">Fermer</button></div>
    <form novalidate wire:submit="{{ $legalForm ? 'fillDocument' : 'save' }}">
        <div class="form-grid">
            <div class="field"><label for="document-project">Dossier projet</label><select id="document-project" wire:model.live="form.client_project_id"><option value="">Sans projet</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }} — {{ $project->client->name }}{{ $project->archived_at ? ' (archivé)' : '' }}</option>@endforeach</select><small>Le client et le devis accepté sont repris du dossier.</small></div>
            <div class="field"><label for="document-type">Document à préparer</label><select id="document-type" wire:model.live="form.type">@foreach(\App\Models\Document::TYPES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="document-mode">Source</label><select id="document-mode" wire:model.live="mode"><option value="upload">Importer un fichier</option><option value="generate">Générer le document</option></select></div>
            @if(empty($form['client_project_id']))
                <div class="field"><label for="document-client">Client</label><select id="document-client" wire:model.live="form.client_id"><option value="">Choisir un client</option>@foreach($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach</select></div>
            @else
                <div class="field"><span>Client du dossier</span><p>{{ $clients->firstWhere('id', (int)$form['client_id'])?->name ?? 'Client associé au projet' }}</p></div>
            @endif
            @if($legalForm && empty($form['quote_id']))
                <div class="field full"><label for="document-quote">Devis accepté à utiliser</label><select id="document-quote" wire:model.live="form.quote_id"><option value="">Choisir le devis</option>@foreach($quotes as $quote)@if((int)$form['client_project_id']===$quote->client_project_id)<option value="{{ $quote->id }}">{{ $quote->number }}</option>@endif @endforeach</select><small>Un devis unique est choisi automatiquement. S’il y en a plusieurs, sélectionne celui dont le contrat doit reprendre les prestations.</small></div>
            @endif
            @if($mode === 'upload')
                <div class="field full"><label for="document-upload">Fichier — PDF, DOCX, XLSX, PNG, JPEG · maximum 20 Mo</label><input id="document-upload" type="file" accept=".pdf,.docx,.xlsx,.png,.jpg,.jpeg" wire:model="upload"><small>Le nom du fichier sert automatiquement de nom au document.</small><span wire:loading wire:target="upload">Transfert du fichier…</span></div>
            @endif
            <details class="field full" @if($errors->any()) open @endif>
                <summary>Informations du document et associations (facultatif)</summary>
                <div class="form-grid">
                    <div class="field"><label for="document-name">Nom du document</label><input id="document-name" wire:model="form.name"><small>Prérempli pour les documents générés et les fichiers importés.</small></div>
                    <div class="field"><label for="document-date">{{ !empty($form['original_id']) ? 'Date de signature / acceptation' : 'Date du document' }}</label><input id="document-date" type="date" wire:model.live="form.document_date"></div>
                    @if(!$legalForm || !empty($form['quote_id']))
                        <div class="field"><label for="document-quote">Devis associé</label><select id="document-quote" wire:model.live="form.quote_id"><option value="">Aucun</option>@foreach($quotes as $quote)@if((int)($form['client_project_id']??0)===$quote->client_project_id)<option value="{{ $quote->id }}">{{ $quote->number }}</option>@endif @endforeach</select></div>
                    @endif
                    <div class="field"><label for="document-invoice">Facture associée</label><select id="document-invoice" wire:model="form.invoice_id"><option value="">Aucune</option>@foreach($invoices as $invoice)@if((int)($form['client_project_id']??0)===$invoice->client_project_id)<option value="{{ $invoice->id }}">{{ $invoice->number }}</option>@endif @endforeach</select></div>
                    @foreach(['description'=>'Description','notes'=>'Notes internes'] as $key=>$label)<div class="field"><label for="document-{{ $key }}">{{ $label }}</label><textarea id="document-{{ $key }}" wire:model="form.{{ $key }}" rows="2"></textarea></div>@endforeach
                </div>
            </details>
            @if($mode === 'generate')
                @if($legalForm)
                    <div class="field full" wire:key="legal-form-{{ $form['type'] }}">@include('livewire.admin.legal-document-fields')</div>
                @else
                    <div class="field full"><label for="document-template">Modèle de texte</label><select id="document-template" wire:model.live="form.template_id"><option value="">Texte libre</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }} — v{{ $template->revision }}</option>@endforeach</select></div>
                    <div class="field full"><label for="document-content">Texte du document</label><textarea id="document-content" wire:model.live.debounce.500ms="form.content" rows="12"></textarea></div>
                @endif
            @endif
        </div>
        <div class="button-row">
            <button class="button button-primary" wire:loading.attr="disabled">{{ $legalForm ? 'Générer le PDF définitif' : 'Enregistrer le document' }}</button>
            @if($legalForm)<button type="button" class="button button-secondary" wire:click="save" wire:loading.attr="disabled">Conserver un brouillon</button>@endif
        </div>
        <span wire:loading wire:target="save,fillDocument">Enregistrement en cours…</span>
    </form>
</section>
