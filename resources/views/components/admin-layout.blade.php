@props(['title' => 'Administration'])
@php
    // Grouped navigation: [label, route, extra active patterns, badge count].
    $badges = [
        'leads' => (int) \App\Models\Lead::openStatusCounts()['new'],
        'reviews' => \App\Models\Testimonial::where('moderation_status', 'pending')->count(),
        'privacy' => \App\Models\PrivacyRequest::whereNull('resolved_at')->count(),
    ];
    \App\Models\AdminTask::syncAutomatic();
    $badges['tasks'] = \App\Models\AdminTask::open()->count();
    $navigation = [
        null => [
            ['Tableau de bord', 'admin.dashboard', [], 0],
            ['À faire', 'admin.tasks', [], $badges['tasks']],
        ],
        'Activité' => [
            ['Prospects', 'admin.leads', ['admin.archives'], $badges['leads']],
            ['Clients', 'admin.clients', [], 0],
            ['Projets', 'admin.client-projects.index', ['admin.client-projects.*'], 0],
        ],
        'Facturation' => [
            ['Devis', 'admin.billing.quotes.index', ['admin.billing.quotes.*'], 0],
            ['Factures', 'admin.billing.invoices.index', ['admin.billing.invoices.*'], 0],
            ['Documents', 'admin.documents', ['admin.documents.*'], 0],
            ['Comptabilité', 'admin.accounting', [], 0],
        ],
        'Site public' => [
            ['Réalisations', 'admin.projects', [], 0],
            ['Témoignages', 'admin.testimonials', [], $badges['reviews']],
            ['Tarifs affichés', 'admin.pricing', [], 0],
        ],
        'Réglages' => [
            ['Prestations et options', 'admin.catalog', [], 0],
            ['Paramètres commerciaux', 'admin.commercial-settings', ['admin.settings.commercial'], 0],
            ['Modèles de documents', 'admin.document-templates', [], 0],
            ['Demandes RGPD', 'admin.privacy-requests', [], $badges['privacy']],
            ['Mon compte', 'profile.edit', [], 0],
            ['Sécurité', 'security.edit', [], 0],
        ],
    ];
@endphp
<!DOCTYPE html><html lang="fr" class="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $title }} — Codenyr Admin</title><link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=codenyr"><meta name="apple-mobile-web-app-title" content="Codenyr"><link rel="icon" href="{{ asset('favicon-48.png') }}">@fonts @vite(['resources/css/app.css','resources/js/app.js']) @livewireStyles</head>
<body class="admin-body"><a class="skip-link" href="#admin-main">Aller au contenu</a>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a href="{{ route('home') }}" class="brand"><img src="{{ asset('images/codenyr.png') }}" alt="Codenyr" width="2172" height="724"></a>
        <form class="admin-search" method="get" action="{{ route('admin.commercial-search') }}" role="search">
            <label class="sr-only" for="admin-search">Rechercher un client, un projet, un devis ou une facture</label>
            <input id="admin-search" type="search" name="q" value="{{ request()->routeIs('admin.commercial-search') ? request('q') : '' }}" placeholder="Rechercher…">
        </form>
        <nav aria-label="Administration">
            @foreach($navigation as $group => $items)
                <div class="admin-nav-group">
                    @if($group)<p class="admin-nav-title">{{ $group }}</p>@endif
                    @foreach($items as [$label, $route, $patterns, $count])
                        @php($active = request()->routeIs($route, ...$patterns))
                        <a href="{{ route($route) }}" @class(['active' => $active]) @if($active) aria-current="page" @endif>
                            <span>{{ $label }}</span>
                            @if($count)<span class="admin-nav-badge" aria-label="{{ $count }} à traiter">{{ $count }}</span>@endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
        <div class="admin-sidebar-footer">
            <a href="{{ route('home') }}">Voir le site <x-ui-icon name="arrow-up-right" /></a>
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Déconnexion</button></form>
        </div>
    </aside>
    <main id="admin-main" class="admin-main">
        @if(\App\Support\TestMode::enabled())<p class="admin-test-banner" role="status"><strong>Mode test local</strong> — les documents peuvent être émis sans SIRET ni informations complètes. Chaque PDF porte la mention « MODE TEST ». Désactivé automatiquement en production.</p>@endif
        {{ $slot }}
    </main>
</div>
@livewireScriptConfig</body></html>
