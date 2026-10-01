<div>
    <p class="eyebrow">Codenyr / Gestion commerciale</p>
    <div class="admin-toolbar"><div><h1>Projets</h1><p>Un dossier central pour chaque travail réalisé pour vos clients.</p></div><button class="button button-primary" wire:click="create">+ Nouveau projet</button></div>
    @if($showForm)
    <section class="admin-panel"><div class="admin-toolbar"><h2>Nouveau projet</h2><button class="button button-secondary" wire:click="$set('showForm', false)">Fermer</button></div>
        @if($sourceLead)<p>Création depuis le prospect #{{ $sourceLead }}. La demande originale et ses avis sont conservés.</p>@endif
        <form wire:submit="save">
            <div class="form-grid">
                <div class="field full"><label for="project-client">Client</label><select id="project-client" wire:model.live="form.client_id"><option value="">Créer un nouveau client</option>@foreach($clients as $client)<option value="{{ $client->id }}">{{ $client->name }} — {{ $client->email }}</option>@endforeach</select></div>
                @if(empty($form['client_id']))
                    @foreach(['name'=>'Entreprise / nom du client','contact'=>'Contact','email'=>'E-mail','phone'=>'Téléphone','address'=>'Adresse'] as $key=>$label)<div class="field"><label for="client-{{ $key }}">{{ $label }}</label><input id="client-{{ $key }}" wire:model="clientForm.{{ $key }}" type="{{ $key === 'email' ? 'email' : 'text' }}"></div>@endforeach
                @endif
                @include('livewire.admin.project-fields')
            </div>
            @include('livewire.admin.commercial-errors')
            <button class="button button-primary" wire:loading.attr="disabled">Créer le dossier</button>
        </form>
    </section>
    @endif
    <div class="admin-toolbar"><input aria-label="Rechercher un projet" placeholder="Projet, client ou e-mail…" wire:model.live.debounce.300ms="search"><select aria-label="Filtrer les projets" wire:model.live="filter"><option value="">Tous les projets non archivés</option><option value="active">Actifs</option><option value="completed">Terminés</option><option value="archived">Archivés</option></select></div>
    <div class="admin-panel table-wrap"><table class="admin-table"><thead><tr><th>Projet / client</th><th>Type</th><th>Statut</th><th>Livraison</th><th></th></tr></thead><tbody>
    @forelse($projects as $project)<tr><td><strong>{{ $project->name }}</strong><small>{{ $project->client->name }}</small></td><td>{{ $project->type }}</td><td><span class="status-pill">{{ $project->archived_at ? 'Archivé' : \App\Models\ClientProject::STATUSES[$project->status] }}</span></td><td>{{ $project->due_on?->format('d/m/Y') ?? 'À définir' }}</td><td><a class="text-link" href="{{ route('admin.client-projects.show', $project) }}">Ouvrir le dossier →</a></td></tr>@empty<tr><td colspan="5">Aucun projet. Créez votre premier dossier ou partez d’un prospect existant.</td></tr>@endforelse
    </tbody></table></div>{{ $projects->links() }}
</div>
