<div>
    <p class="eyebrow">CRM / Codenyr</p>
    <h1>{{ $archived ? 'Archives' : 'Prospects' }}</h1>
    <p>{{ $archived ? 'Les sites terminés sont conservés ici. Restaurez un projet pour reprendre son suivi.' : 'Pour archiver un site terminé, acceptez le devis puis confirmez sa livraison dans le détail du projet.' }}</p>
    @if(session('success'))<div class="flash" role="status">{{ session('success') }}</div>@endif
    @error('archive')<div class="form-alert" role="alert">{{ $message }}</div>@enderror
    <div class="admin-toolbar">
        <input aria-label="Rechercher un prospect" wire:model.live.debounce.300ms="search" placeholder="Nom, entreprise ou e-mail…">
        <select wire:model.live="filter" aria-label="Filtrer par statut"><option value="">Tous les statuts</option>@foreach(\App\Models\Lead::STATUSES as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
    </div>
    @if($lead)
        <section class="admin-panel">
            <div class="admin-toolbar"><h2>{{ $lead->firstname }} {{ $lead->lastname }}</h2><button type="button" class="button button-secondary" wire:click="close">Fermer</button></div>
            <div class="admin-details">
                <div><strong>Coordonnées</strong><p>{{ $lead->company }}<br><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a><br>{{ $lead->phone }}</p></div>
                <div><strong>Projet</strong><p>{{ $lead->project_type }}@if($lead->budget)<br>Ancien budget renseigné : {{ $lead->budget }}@endif<br>Délai : {{ $lead->desired_date ?: 'À définir' }}<br>{{ $lead->website }}</p></div>
                @if($lead->features)<div><strong>Fonctionnalités demandées précédemment</strong><p>{{ implode(', ', $lead->features) }}</p></div>@endif
                <div><strong>Demande</strong><p>{{ $lead->description }}</p></div>
            </div>
            @if($lead->archived_at)
                <p>Archivé le {{ $lead->archived_at->format('d/m/Y') }} · Site livré le {{ $lead->delivered_at?->format('d/m/Y') }}</p>
                @if($lead->notes)<div><strong>Notes internes</strong><p>{{ $lead->notes }}</p></div>@endif
                <button type="button" class="button button-secondary" wire:click="restore({{ $lead->id }})" wire:loading.attr="disabled">Restaurer le projet</button>
            @else
                <form wire:submit="save" style="margin-top:25px">
                    <div class="form-grid">
                        <div class="field"><label for="lead-status">Statut</label><select id="lead-status" wire:model="status">@foreach(\App\Models\Lead::STATUSES as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>@error('status')<span class="error">{{ $message }}</span>@enderror</div>
                        @include('livewire.admin.lead-customer-fields')
                        <div class="field full"><label for="lead-notes">Notes internes</label><textarea id="lead-notes" wire:model="notes" rows="4"></textarea>@error('notes')<span class="error">{{ $message }}</span>@enderror</div>
                    </div>
                    <button type="submit" class="button button-primary" wire:loading.attr="disabled">Enregistrer</button>
                </form>
                @if($lead->status === 'accepted' && $lead->delivered_at)
                    <button type="button" class="button button-secondary" wire:click="archive({{ $lead->id }})" wire:loading.attr="disabled">Archiver le projet terminé</button>
                @endif
            @endif
        </section>
    @endif
    <div class="admin-panel table-wrap">
        <table class="admin-table">
            <thead><tr><th>Contact</th><th>Projet</th><th>Statut</th><th>{{ $archived ? 'Archivé le' : 'Reçu le' }}</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($leads as $item)
                    <tr wire:key="lead-{{ $item->id }}">
                        <td>{{ $item->firstname }} {{ $item->lastname }}<small>{{ $item->company }}</small><small>{{ $item->email }}</small></td>
                        <td>{{ $item->project_type }}</td>
                        <td><span class="status-pill">{{ \App\Models\Lead::STATUSES[$item->status] }}</span>@if($item->delivered_at)<small>Site livré</small>@endif</td>
                        <td>{{ ($item->archived_at ?? $item->created_at)->format('d/m/Y') }}</td>
                        <td>
                            <button type="button" wire:click="open({{ $item->id }})">Consulter</button>
                            @if($archived)
                                <button type="button" wire:click="restore({{ $item->id }})" wire:loading.attr="disabled">Restaurer</button>
                            @elseif($item->status === 'accepted' && $item->delivered_at)
                                <button type="button" wire:click="archive({{ $item->id }})" wire:loading.attr="disabled">Archiver</button>
                            @endif
                            <button type="button" class="danger" wire:click="delete({{ $item->id }})" wire:confirm="Supprimer définitivement ce prospect et ses notes ?">Supprimer</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ $archived ? 'Aucun projet archivé ne correspond à votre recherche.' : 'Aucun prospect ne correspond à votre recherche.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $leads->links() }}</div>
</div>
