<div x-data="{
    focusDocumentField(id) {
        const field = document.getElementById(id) || document.getElementById('legal-checklist');
        if (!field) return;
        let parent = field.parentElement;
        while (parent) {
            if (parent.tagName === 'DETAILS') parent.open = true;
            parent = parent.parentElement;
        }
        if (!field.matches('input, select, textarea, button, a')) field.setAttribute('tabindex', '-1');
        field.focus({ preventScroll: true });
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },
    showDocumentErrors(fields) {
        this.$root.querySelectorAll('[data-document-error]').forEach(el => el.remove());
        this.$root.querySelectorAll('[aria-invalid=true]').forEach(el => {
            el.removeAttribute('aria-invalid');
            el.removeAttribute('aria-describedby');
            el.style.removeProperty('outline');
        });
        fields.forEach((error, index) => {
            const field = document.getElementById(error.id);
            if (!field) return;
            field.setAttribute('aria-invalid', 'true');
            field.style.outline = '2px solid #dc2626';
            const message = document.createElement('small');
            message.id = 'document-field-error-' + index;
            message.dataset.documentError = '';
            message.setAttribute('role', 'alert');
            message.style.color = '#dc2626';
            message.textContent = error.message;
            field.setAttribute('aria-describedby', message.id);
            field.insertAdjacentElement('afterend', message);
        });
        if (fields.length) this.focusDocumentField(fields[0].id);
    }
}" x-on:document-validation-failed="$nextTick(() => showDocumentErrors($event.detail.fields))"><p class="eyebrow">Codenyr / Gestion commerciale</p><div class="admin-toolbar"><h1>Documents</h1>@if($projectFilter)<a class="text-link" href="{{ route('admin.client-projects.show',$projectFilter) }}">Retour au projet</a>@endif<div class="button-row"><a class="button button-secondary" href="{{ route('admin.document-templates') }}">Modèles de textes</a><button class="button button-primary" wire:click="startDocument('contract')">Créer un contrat</button><button class="button button-secondary" wire:click="startDocument('terms')">Préparer les CGV</button><button class="button button-secondary" wire:click="create">Importer un fichier</button></div></div>@include('livewire.admin.commercial-errors')
@if($showForm)@include('livewire.admin.document-form')@endif
@if($preview)<section class="admin-panel"><div class="admin-toolbar"><h2>{{ $preview->name }}</h2><button class="button button-secondary" wire:click="$set('previewId',null)">Fermer l’aperçu</button></div><p>Document #{{ $preview->id }} · Version {{ $preview->snapshot['revision'] ?? 1 }}</p>@if(!empty($preview->snapshot['previous_document_id']))<a class="text-link" href="{{ route('admin.documents',['project'=>$preview->client_project_id,'preview'=>$preview->snapshot['previous_document_id']]) }}">Consulter la version précédente</a>@endif @if($preview->original_id)<a class="text-link" href="{{ route('admin.documents',['project'=>$preview->client_project_id,'preview'=>$preview->original_id]) }}">Consulter l’original signé</a>@endif<p>{{ $preview->description }}</p><details><summary>Consulter le texte du document</summary><p class="preserve-lines">{{ $preview->content }}</p></details><p class="preserve-lines">{{ $preview->notes }}</p>@if($preview->path)@if($preview->mime === 'application/pdf')<iframe class="commercial-document-preview" title="Aperçu du document PDF" src="{{ route('admin.documents.show',$preview) }}"></iframe>@elseif(in_array($preview->mime,['image/png','image/jpeg']))<img class="commercial-image-preview" alt="{{ $preview->name }}" src="{{ route('admin.documents.show',$preview) }}">@endif<a class="button button-primary" href="{{ route('admin.documents.download',$preview) }}">Télécharger</a>@elseif($preview->content)<iframe class="commercial-document-preview" title="Aperçu PDF avant génération" src="{{ route('admin.documents.show',$preview) }}"></iframe>@if(!in_array($preview->type,['terms','contract']))<button class="button button-primary" wire:click="generate({{ $preview->id }})">Générer le PDF</button>@endif@endif @if(in_array($preview->type,['terms','contract']) && !$preview->original_id)
<p>{{ $preview->finalized_at ? 'Document définitif — données et PDF figés' : ($preview->contract_data !== null ? 'BROUILLON — DOCUMENT NON FINALISÉ' : 'Document historique — finalisation non attestée') }}</p>
@if(!$preview->finalized_at && (!$preview->path || $preview->contract_data !== null))<div class="button-row"><button class="button button-secondary" wire:click="review({{ $preview->id }})">Vérification du document</button><button class="button button-secondary" wire:click="revise({{ $preview->id }})">Compléter / créer une nouvelle version</button><button class="button button-secondary" wire:click="draft({{ $preview->id }})">Générer le brouillon</button></div>@endif
@if($legalReview && $reviewId === $preview->id)<section><h3>Vérification du document</h3><p>Document complété à {{ $legalReview['percent'] }} %</p>@if($legalReview['errors'])<p>Impossible de finaliser le document.</p><ul>@foreach($legalReview['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>@endif @foreach($legalReview['warnings'] as $warning)<p>{{ $warning }}</p>@endforeach<details><summary>Relire le document avant finalisation</summary><p class="preserve-lines">{{ $legalReview['content'] }}</p></details>@if(!$preview->finalized_at)<button class="button button-primary" wire:click="generate({{ $preview->id }})" wire:loading.attr="disabled" @disabled(count($legalReview['errors'])>0)>Finaliser et générer le PDF</button>@endif</section>@endif
@endif
@if($preview->versions->count()>1)<h3>Versions conservées</h3>@foreach($preview->versions as $version)<p><a class="text-link" href="{{ route('admin.documents.version',$version) }}">Version {{ $version->version }} — {{ $version->created_at->format('d/m/Y H:i') }}</a></p>@endforeach @endif</section>@endif
<div class="admin-panel"><div class="form-grid"><div class="field"><label for="doc-search">Recherche</label><input id="doc-search" wire:model.live.debounce.300ms="search" placeholder="Nom, numéro, client ou projet…"></div><div class="field"><label for="doc-project-filter">Projet</label><select id="doc-project-filter" wire:model.live="projectFilter"><option value="">Tous</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></div><div class="field"><label for="doc-client-filter">Client</label><select id="doc-client-filter" wire:model.live="clientFilter"><option value="">Tous</option>@foreach($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach</select></div><div class="field"><label for="doc-type-filter">Type</label><select id="doc-type-filter" wire:model.live="typeFilter"><option value="">Tous</option>@foreach(\App\Models\Document::TYPES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label for="doc-year">Année</label><input id="doc-year" wire:model.live.debounce.400ms="yearFilter" placeholder="2026" inputmode="numeric"></div><div class="field"><label for="doc-date-filter">Date</label><input id="doc-date-filter" type="date" wire:model.live="dateFilter"></div><div class="field"><label for="doc-status-filter">Statut</label><select id="doc-status-filter" wire:model.live="statusFilter"><option value="">Disponibles</option><option value="signed">Versions signées</option><option value="archived">Archivés</option></select></div></div></div>
<div class="admin-panel table-wrap">@include('livewire.admin.document-table')</div>{{ $documents->links() }}</div>

