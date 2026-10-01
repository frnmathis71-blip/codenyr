<div>
    <p class="eyebrow">Données personnelles</p><h1>Demandes RGPD</h1>
    @include('livewire.admin.commercial-errors')
    <p>Examinez la demande, réalisez les opérations nécessaires, puis renseignez votre réponse. Clôturer ne supprime aucune donnée. Le délai initial est d’un mois ; toute prolongation justifiée doit être expliquée au demandeur dans ce délai. Les demandes reçues par e-mail doivent également être suivies.</p>
    @forelse($requests as $request)
        <section class="admin-panel" wire:key="privacy-{{ $request->id }}">
            <h2>#{{ $request->id }} · {{ \App\Models\PrivacyRequest::TYPES[$request->type] }}</h2>
            <p>{{ $request->email }} · Reçue le {{ $request->created_at->format('d/m/Y') }} · Échéance {{ $request->due_at->format('d/m/Y') }}
                @if(!$request->resolved_at && $request->due_at->isPast())<strong> — En retard</strong>@endif
            </p>
            <p class="preserve-lines">{{ $request->message }}</p>
            @if($request->resolved_at)
                <p>Réponse enregistrée le {{ $request->resolved_at->format('d/m/Y') }}</p><p class="preserve-lines">{{ $request->response }}</p>
            @else
                <form wire:submit="resolve({{ $request->id }})">
                    <div class="field"><label for="privacy-answer-{{ $request->id }}">Réponse au client et opérations réalisées</label><textarea id="privacy-answer-{{ $request->id }}" wire:model="responses.{{ $request->id }}" rows="4" required></textarea></div>
                    <p>La réponse est consultable dans son espace. Si le compte est supprimé, répondez également à l’adresse indiquée.</p>
                    <button class="button button-primary" wire:loading.attr="disabled">Enregistrer la réponse et clôturer</button>
                </form>
            @endif
        </section>
    @empty
        <p>Aucune demande enregistrée.</p>
    @endforelse
    {{ $requests->links() }}
</div>
