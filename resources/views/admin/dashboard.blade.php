<x-admin-layout title="Tableau de bord">
@php
    $inProgress = $counts['new'] + $counts['contacted'] + $counts['meeting'];
    // One prioritised to-do list: [count, label, detail, route, action label].
    $todo = array_filter([
        [$counts['new'], 'nouveau(x) prospect(s) à contacter', 'Reçus via le formulaire du site.', route('admin.leads'), 'Voir les prospects'],
        [$commercial['unpaid'], 'facture(s) impayée(s)', 'Finalisées, avec un reste à encaisser.', route('admin.billing.invoices.index'), 'Voir les factures'],
        [$commercial['pending_quotes'], 'devis en attente de réponse', 'Envoyés, ni acceptés ni refusés.', route('admin.billing.quotes.index'), 'Voir les devis'],
        [$pendingReviewCount, 'avis client(s) à modérer', 'En attente avant publication sur le site.', route('admin.testimonials'), 'Modérer'],
        [$privacyRequestCount, 'demande(s) RGPD à traiter', 'À traiter dans le délai légal d’un mois.', route('admin.privacy-requests'), 'Traiter'],
    ], fn ($item) => $item[0] > 0);
    // Project alerts (signatures, deposits, deadlines…) grouped into one row per project.
    $projectAlerts = collect($commercial['actions'])->groupBy('project_id');
@endphp

<div class="dash-head">
    <div>
        <p class="eyebrow">{{ ucfirst(now()->locale('fr')->isoFormat('dddd D MMMM YYYY')) }}</p>
        <h1>Tableau de bord</h1>
    </div>
    <div class="dash-quick" aria-label="Actions rapides">
        <a class="button button-primary" href="{{ route('admin.billing.quotes.create') }}">Nouveau devis</a>
        <a class="button button-secondary" href="{{ route('admin.billing.invoices.create') }}">Nouvelle facture</a>
    </div>
</div>

<section class="admin-panel dash-todo" aria-labelledby="todo-title">
    <h2 id="todo-title">À faire</h2>
    @if($todo || $projectAlerts->isNotEmpty())
        <ul>
            @foreach($todo as [$count, $label, $detail, $url, $action])
                <li><strong class="dash-count">{{ $count }}</strong><div><p>{{ $label }}</p><small>{{ $detail }}</small></div><a class="button button-secondary" href="{{ $url }}">{{ $action }}</a></li>
            @endforeach
            @foreach($projectAlerts as $projectId => $alerts)
                <li><strong class="dash-count dash-count-alert">{{ $alerts->count() }}</strong><div><p>Projet {{ $alerts->first()['name'] }}</p><ul class="dash-alerts">@foreach($alerts as $item)<li>{{ $item['alert'] }}</li>@endforeach</ul></div><a class="button button-secondary" href="{{ route('admin.client-projects.show', $projectId) }}">Ouvrir le projet</a></li>
            @endforeach
        </ul>
    @else
        <p class="dash-empty">Rien d’urgent. Tout est à jour.</p>
    @endif
</section>

<div class="admin-stats dash-stats">
    @foreach([
        ['Prospects en cours', $inProgress, route('admin.leads')],
        ['Devis en attente', $commercial['pending_quotes'], route('admin.billing.quotes.index')],
        ['Projets actifs', $commercial['active'], route('admin.client-projects.index')],
        ['Maintenances actives', $commercial['maintenance'], route('admin.client-projects.index')],
        ['Réalisations publiées', $projectCount, route('admin.projects')],
    ] as [$label, $value, $url])
        <a class="stat-card" href="{{ $url }}"><span>{{ $label }}</span><strong>{{ $value }}</strong></a>
    @endforeach
</div>

<section class="admin-panel">
    <div class="admin-toolbar"><h2>Derniers prospects</h2><a class="text-link" href="{{ route('admin.leads') }}">Tous les prospects <x-ui-icon name="arrow-right" /></a></div>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>Contact</th><th>Projet</th><th>Statut</th><th>Reçu le</th></tr></thead><tbody>@forelse($leads as $lead)<tr><td>{{ $lead->firstname }} {{ $lead->lastname }}<small>{{ $lead->company }}</small></td><td>{{ $lead->project_type }}</td><td><span class="status-pill">{{ \App\Models\Lead::STATUSES[$lead->status] }}</span></td><td>{{ $lead->created_at->format('d/m/Y') }}</td></tr>@empty<tr><td colspan="4">Aucun prospect pour le moment.</td></tr>@endforelse</tbody></table></div>
</section>
</x-admin-layout>
