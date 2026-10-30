<div>
    <p class="eyebrow">CRM / Codenyr</p>
    <div class="dash-head">
        <div>
            <h1>Démarchage</h1>
            <p>Les entreprises à contacter vous-même, par téléphone, sur place ou par courrier. {{ $total }} entreprise(s) suivie(s).</p>
        </div>
        <div class="dash-quick"><button type="button" class="button button-primary" wire:click="create">Ajouter une entreprise</button></div>
    </div>
    @if(session('success'))<div class="flash" role="status">{{ session('success') }}</div>@endif

    <div class="admin-stats dash-stats outreach-stats">
        @foreach([
            ['À contacter', $counts['todo'], 'todo'],
            ['Relances à faire', $due, 'due'],
            ['Rendez-vous', $counts['meeting'], 'meeting'],
            ['Devis envoyés', $counts['quote_sent'], 'quote_sent'],
            ['Gagnés', $counts['won'], 'won'],
        ] as [$label, $value, $key])
            <button type="button" @class(['stat-card', 'is-active' => $filter === $key]) wire:click="$set('filter', '{{ $filter === $key ? '' : $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"><span>{{ $label }}</span><strong>{{ $value }}</strong></button>
        @endforeach
    </div>

    @if($showForm)
        <section class="admin-panel" aria-labelledby="outreach-form-title">
            <div class="admin-toolbar"><h2 id="outreach-form-title">{{ $editing ? $name : 'Nouvelle entreprise' }}</h2><button type="button" class="button button-secondary" wire:click="close">Fermer</button></div>
            <form wire:submit="save">
                <div class="form-grid">
                    <div class="field"><label for="outreach-name">Entreprise</label><input id="outreach-name" wire:model="name" maxlength="160" required>@error('name')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="outreach-activity">Activité</label><input id="outreach-activity" wire:model="activity" maxlength="160">@error('activity')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="outreach-address">Adresse</label><input id="outreach-address" wire:model="address" maxlength="255">@error('address')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="outreach-phone">Téléphone</label><input id="outreach-phone" type="tel" wire:model="phone" maxlength="40">@error('phone')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="outreach-offer">Offre conseillée</label><select id="outreach-offer" wire:model="offer"><option value="">À définir</option>@foreach(config('codenyr.offers') as $key => $item)<option value="{{ $key }}">{{ $item['name'] }} · {{ $item['price'] }} €</option>@endforeach</select>@error('offer')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="outreach-status">Statut</label><select id="outreach-status" wire:model.live="status">@foreach(\App\Models\Outreach::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>@error('status')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="outreach-channel">Moyen de contact</label><select id="outreach-channel" wire:model="channel"><option value="">—</option>@foreach(\App\Models\Outreach::CHANNELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>@error('channel')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="outreach-last">Dernier contact</label><input id="outreach-last" type="date" wire:model="last_contact_on">@error('last_contact_on')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="outreach-next">Prochaine action</label><input id="outreach-next" wire:model="next_action" maxlength="200" placeholder="Rappeler, passer sur place…">@error('next_action')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="outreach-next-on">Date de la prochaine action</label><input id="outreach-next-on" type="date" wire:model="next_action_on">@error('next_action_on')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field full"><label for="outreach-notes">Notes</label><textarea id="outreach-notes" wire:model="notes" rows="4" maxlength="5000"></textarea>@error('notes')<span class="error">{{ $message }}</span>@enderror</div>
                </div>
                <button type="submit" class="button button-primary" wire:loading.attr="disabled">Enregistrer</button>
            </form>
        </section>
    @endif

    <div class="admin-toolbar">
        <input aria-label="Rechercher une entreprise" wire:model.live.debounce.300ms="search" placeholder="Nom, activité ou adresse…">
        <select wire:model.live="filter" aria-label="Filtrer par statut"><option value="">Tous les statuts</option><option value="due">Relances à faire</option>@foreach(\App\Models\Outreach::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
    </div>
    <div class="admin-panel table-wrap">
        <table class="admin-table">
            <thead><tr><th>Entreprise</th><th>Téléphone</th><th>Offre</th><th>Statut</th><th>Prochaine action</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($outreaches as $item)
                    <tr wire:key="outreach-{{ $item->id }}">
                        <td>{{ $item->name }}<small>{{ $item->activity }}</small><small>{{ $item->address }}</small></td>
                        <td>@if($item->phone)<a class="text-link outreach-phone" href="tel:{{ preg_replace('/[^0-9+]/', '', $item->phone) }}">{{ $item->phone }}</a>@else<small>{{ $item->channel ? \App\Models\Outreach::CHANNELS[$item->channel] : '—' }}</small>@endif</td>
                        <td>{{ $item->offerLabel() ?? '—' }}</td>
                        <td><select class="status-pill outreach-status" aria-label="Statut de {{ $item->name }}" wire:change="setStatus({{ $item->id }}, $event.target.value)">@foreach(\App\Models\Outreach::STATUSES as $value => $label)<option value="{{ $value }}" @selected($item->status === $value)>{{ $label }}</option>@endforeach</select>@if($item->last_contact_on)<small>Contacté le {{ $item->last_contact_on->format('d/m/Y') }}</small>@endif</td>
                        <td>
                            {{ $item->next_action ?? '—' }}
                            @if($item->next_action_on)<small @class(['outreach-due' => $item->isDue()])>{{ $item->isDue() ? ($item->next_action_on->isToday() ? 'Aujourd’hui' : 'En retard depuis le '.$item->next_action_on->format('d/m/Y')) : 'Le '.$item->next_action_on->format('d/m/Y') }}</small>@endif
                        </td>
                        <td>
                            <button type="button" wire:click="edit({{ $item->id }})">Modifier</button>
                            <button type="button" class="danger" wire:click="delete({{ $item->id }})" wire:confirm="Supprimer {{ $item->name }} de la liste ?">Supprimer</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Aucune entreprise ne correspond à votre recherche.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
