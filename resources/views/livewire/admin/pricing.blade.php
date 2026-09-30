<div>
    <p class="eyebrow">Codenyr / Administration</p>
    <h1>Tarifs</h1>
    <p>Modifiez vos prix en euros. Ils seront mis à jour sur l’accueil, les services et la page Tarifs dès l’enregistrement.</p>
    @if(session('success'))<div class="flash" role="status">{{ session('success') }}</div>@endif
    <form wire:submit="save">
        @foreach(['offer' => 'Création de sites — prix de départ', 'maintenance' => 'Maintenance — prix mensuels'] as $group => $title)
            <section class="admin-panel">
                <h2>{{ $title }}</h2>
                <div class="form-grid" style="margin-top:24px">
                    @foreach($plans as $key => $plan)
                        @if($plan['group'] === $group)
                            <div class="field" wire:key="price-{{ $key }}">
                                <label for="price-{{ $key }}">{{ $plan['name'] }} — {{ $group === 'maintenance' ? '€/mois' : '€' }}</label>
                                <input id="price-{{ $key }}" type="text" inputmode="decimal" wire:model="prices.{{ $key }}" required aria-describedby="price-help-{{ $key }}" @error('prices.'.$key) aria-invalid="true" @enderror>
                                <small id="price-help-{{ $key }}">{{ $group === 'offer' || ($plan['starting_from'] ?? false) ? 'Affiché avec la mention « À partir de ».' : 'Montant de la formule mensuelle.' }}</small>
                                @error('prices.'.$key)<span class="error" role="alert">{{ $message }}</span>@enderror
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach
        <div class="button-row"><button type="submit" class="button button-primary" wire:loading.attr="disabled"><span wire:loading.remove wire:target="save">Enregistrer les tarifs</span><span wire:loading wire:target="save">Enregistrement…</span></button><a class="button button-secondary" href="{{ route('pricing') }}" target="_blank" rel="noopener">Voir les tarifs publics <span class="mobile-decoration" aria-hidden="true">↗</span></a></div>
    </form>
</div>
