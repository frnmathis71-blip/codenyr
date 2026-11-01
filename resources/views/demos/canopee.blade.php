@php
    $pages = ['accueil'=>'Accueil', 'studio'=>'Le studio', 'realisations'=>'Réalisations', 'journal'=>'Journal', 'gestion'=>'Administration'];
    $services = [['Conception paysagère', 'Un plan d’ensemble qui relie les circulations, les usages et les ambiances du jardin.'], ['Terrasses', 'Prolonger la maison dehors avec des matières naturelles et une composition végétale structurée.'], ['Jardins de ville', 'Tirer parti de chaque mètre carré pour créer un extérieur intime et vivant.']];
    $studio = [
        'key' => 'codenyr-canopee-preview-v3',
        'product' => 'Site Pro',
        'demo' => 'Canopée (site Pro)',
        'brand' => 'canopée',
        'accent' => '--accent',
        'palettes' => [
            'mineral' => ['Lin & feuillage', '#f1f2e9', '#293c2b', '#587343'],
            'olive' => ['Olive & lin', '#f1f2e9', '#303d2e', '#526a3c'],
            'blue' => ['Bleu architecte', '#edf2f5', '#233d50', '#346b90'],
            'rose' => ['Rose poudré', '#faf0ed', '#4c3038', '#965765'],
            'dark' => ['Obsidienne', '#202625', '#f1eee5', '#dcc3a0'],
        ],
        'groups' => [
            'Typographie' => [
                'type' => ['Style des titres', ['modern' => 'Contemporaine', 'signature' => 'Signature éditoriale', 'classic' => 'Classique']],
                'scale' => ['Taille des titres', ['normal' => 'Équilibrée', 'large' => 'Affirmée', 'small' => 'Discrète']],
            ],
            'Mise en page' => [
                'layout' => ['Présentation d’accueil', ['immersive' => 'Immersive — photo plein écran', 'framed' => 'Photo encadrée', 'split' => 'Deux colonnes']],
                'nav' => ['Navigation', ['burger' => 'Menu déroulant', 'bar' => 'Barre horizontale']],
                'cards' => ['Présentation des contenus', ['tiles' => 'Cartes', 'grid' => 'Grille', 'list' => 'Liste éditoriale', 'mosaic' => 'Mosaïque']],
                'width' => ['Largeur du contenu', ['large' => 'Large', 'narrow' => 'Resserrée']],
                'density' => ['Espacement', ['airy' => 'Généreux', 'compact' => 'Compact']],
            ],
            'Détails' => [
                'decor' => ['Décor', ['rich' => 'Vivant — animations et grain', 'flat' => 'Sobre — sans effet']],
                'corners' => ['Formes', ['rounded' => 'Arrondis', 'square' => 'Angles droits']],
                'buttons' => ['Boutons', ['pill' => 'Arrondis', 'solid' => 'Pleins', 'outline' => 'Contours']],
                'photos' => ['Traitement des photos', ['natural' => 'Naturel', 'warm' => 'Chaleureux', 'mono' => 'Noir et blanc']],
            ],
        ],
        'presets' => [
            'studio' => ['Studio', 'Grande photo et menu discret', ['palette' => 'mineral', 'type' => 'modern', 'scale' => 'normal', 'layout' => 'immersive', 'nav' => 'burger', 'cards' => 'tiles', 'corners' => 'rounded', 'buttons' => 'pill', 'photos' => 'natural']],
            'editorial' => ['Éditorial', 'Classique, aéré, typographique', ['palette' => 'olive', 'type' => 'signature', 'scale' => 'large', 'layout' => 'split', 'nav' => 'bar', 'cards' => 'list', 'corners' => 'square', 'buttons' => 'solid', 'photos' => 'natural']],
            'atelier' => ['Atelier', 'Bleu, net et structuré', ['palette' => 'blue', 'type' => 'modern', 'scale' => 'small', 'layout' => 'framed', 'nav' => 'bar', 'cards' => 'grid', 'corners' => 'square', 'buttons' => 'outline', 'photos' => 'natural']],
            'nuit' => ['Nuit', 'Sombre et immersif', ['palette' => 'dark', 'type' => 'classic', 'scale' => 'large', 'layout' => 'immersive', 'nav' => 'burger', 'cards' => 'mosaic', 'corners' => 'rounded', 'buttons' => 'outline', 'photos' => 'warm']],
        ],
        'defaults' => ['decor' => 'rich', 'palette' => 'mineral', 'type' => 'modern', 'scale' => 'normal', 'layout' => 'immersive', 'nav' => 'burger', 'cards' => 'tiles', 'width' => 'large', 'density' => 'airy', 'corners' => 'rounded', 'buttons' => 'pill', 'photos' => 'natural'],
    ];
@endphp
<!DOCTYPE html><html lang="fr" @foreach($studio['defaults'] as $setting => $value) data-{{ $setting }}="{{ $value }}" @endforeach><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $pages[$page] ?? "Connexion" }} — Canopée · Démonstration Codenyr</title><script>document.documentElement.classList.add('js')</script>@fonts @vite('resources/js/demo-pro.js')</head><body class="page-{{ $page }}">
<a class="skip" href="#main">Aller au contenu</a>
<div class="demo-banner"><span>CANOPÉE · Concept fictif par Codenyr</span><a href="{{ route('services') }}#pro">Retour aux services <x-ui-icon name="arrow-up-right" /></a></div>
<header class="header">
    <button class="burger" type="button" aria-controls="navigation" aria-expanded="false" hidden><span class="burger-lines" aria-hidden="true"><i></i><i></i><i></i></span><span class="burger-label">Menu</span></button>
    <a class="brand" href="{{ route('demo.pro') }}"><b data-brand>canopée</b><span>Studio de paysage</span></a>
    <a class="header-cta" href="{{ route('demo.pro', ['page' => 'gestion']) }}">Espace pro</a>
    <nav id="navigation" class="menu" aria-label="Navigation principale"><ol>@foreach($pages as $slug => $label)<li><a href="{{ route('demo.pro', ['page' => $slug]) }}" @if($page === $slug) aria-current="page" @endif>{{ $label }}</a></li>@endforeach</ol></nav>
</header>

<main id="main">
@if($page === 'accueil')
<section class="hero">
    <figure class="hero-media"><img src="{{ asset('images/canopee/jardin.webp') }}" alt="Outils de jardinage et terreau sur une table" width="1400" height="1000" fetchpriority="high"></figure>
    <div class="hero-content">
        <p class="eyebrow">Studio de paysage · Jardins vivants</p>
        <h1>Faire place<br><em>au vivant.</em></h1>
        <div class="hero-bottom">
            <a class="hero-cta" href="{{ route('demo.pro', ['page' => 'realisations']) }}"><span><small>Réalisations</small>Découvrir les jardins</span><x-ui-icon name="arrow-right" /></a>
            <a class="scroll-cue" href="#suite"><span>Découvrir</span><x-ui-icon name="arrow-down" /></a>
        </div>
    </div>
</section>
<section id="suite" class="wrap intro section">
    <p class="eyebrow" data-reveal>Un site qui vit avec vous</p>
    <div data-reveal><h2 class="statement">Publiez vos contenus, suivez les demandes et devis, gérez vos clients et planifiez vos rendez-vous. <em>Un espace professionnel complet, au fil des saisons.</em></h2><a class="text-link" href="{{ route('demo.pro', ['page' => 'gestion']) }}">Essayer la gestion des contenus <x-ui-icon name="arrow-right" /></a></div>
</section>
<section class="wrap section">
    <div class="section-head" data-reveal><div><p class="eyebrow">Sélection</p><h2>Les derniers <em>jardins.</em></h2></div><a class="text-link" href="{{ route('demo.pro', ['page' => 'realisations']) }}">Toutes les réalisations <x-ui-icon name="arrow-right" /></a></div>
    <div class="projects" data-collection="projects" data-limit="3"></div>
</section>
<section class="wrap section expertise-list">
    <div class="section-head" data-reveal><div><p class="eyebrow">Savoir-faire</p><h2>Trois façons <em>de cultiver.</em></h2></div></div>
    @foreach($services as $service)<a class="expertise-row" data-reveal href="{{ route('demo.pro', ['page' => 'studio']) }}"><span class="num">0{{ $loop->iteration }}</span><h3>{{ $service[0] }}</h3><p>{{ $service[1] }}</p><x-ui-icon name="arrow-up-right" /></a>@endforeach
</section>
@elseif($page === 'studio')
<section class="wrap page-head"><p class="eyebrow">Le studio / Notre approche</p><h1>Penser les lieux.<br><em>Respecter leur nature.</em></h1><p class="lead">Canopée est un studio fictif imaginé autour d’une conviction : un jardin se conçoit autant pour ce qu’il est aujourd’hui que pour ce qu’il deviendra.</p></section>
<figure class="wrap page-banner"><img src="{{ asset('images/canopee/jardin.webp') }}" alt="Terreau et outils pour préparer des plantations" width="1400" height="1000"></figure>
<section class="wrap editorial section"><p class="eyebrow" data-reveal>Notre méthode</p><div data-reveal><h2>Observer avant<br><em>de dessiner.</em></h2><p>Les usages, la lumière et les changements de saison donnent le point de départ. Le dessin relie ensuite les espaces, ménage les perspectives et compose avec le végétal.</p><p>Du petit extérieur urbain au jardin familial, chaque projet cherche un équilibre entre lieux de vie, matières naturelles et plantations.</p></div></section>
<section class="wrap section pillars"><article data-reveal><span>01 / Concevoir</span><h3>Un plan d’ensemble.</h3><p>Relier les circulations, les usages et les différentes ambiances.</p></article><article data-reveal><span>02 / Composer</span><h3>Une palette vivante.</h3><p>Associer les textures, les volumes et les couleurs au fil des saisons.</p></article><article data-reveal><span>03 / Accompagner</span><h3>Un lieu qui évolue.</h3><p>Imaginer la place de chaque espace dans le quotidien de ses habitants.</p></article></section>
@elseif($page === 'realisations' || $page === 'journal')
<section class="wrap page-head"><p class="eyebrow">{{ $page === 'journal' ? 'Journal / Regards sur le paysage' : 'Réalisations / Inspirations paysagères' }}</p><h1>{{ $page === 'journal' ? 'Les idées prennent' : 'Des jardins pour' }}<br><em>{{ $page === 'journal' ? 'racine ici.' : 'les jours heureux.' }}</em></h1><p class="lead">{{ $page === 'journal' ? 'Conseils, inspirations et nouvelles du studio. Une collection que vous pouvez enrichir depuis la gestion de démonstration.' : 'Une sélection de projets fictifs, illustrés par des photographies d’ambiance. Découvrez leurs intentions ou ajoutez votre propre exemple.' }}</p></section>
<section class="wrap section"><label class="search-label" for="collection-search">Rechercher {{ $page === 'journal' ? 'un article' : 'une réalisation' }}</label><input class="collection-search" id="collection-search" type="search" placeholder="Un titre, une catégorie…"><div class="projects" data-collection="{{ $page === 'journal' ? 'articles' : 'projects' }}"></div><p class="collection-status" role="status"></p></section>
@elseif($page === 'connexion')
<section class="wrap section demo-login"><div><p class="eyebrow">CANOPÉE / ESPACE PROFESSIONNEL</p><h1>Bienvenue<br><em>au studio.</em></h1><p class="lead">Un espace pour piloter votre activité et accompagner vos clients.</p><div class="contact-note"><strong>Accès de démonstration</strong><p>Identifiants fictifs préremplis. Ce parcours simule une connexion ; les données restent dans votre navigateur.</p><p>admin@canopee.demo · Canopee2026!</p></div></div><form id="demo-login" data-admin-url="{{ route('demo.pro', ['page'=>'gestion']) }}"><h2>Se connecter</h2><label for="demo-email">Adresse e-mail</label><input id="demo-email" type="email" value="admin@canopee.demo" required autocomplete="off"><label for="demo-password">Mot de passe</label><div class="password-field"><input id="demo-password" type="password" value="Canopee2026!" required autocomplete="off"><button type="button" id="show-password">Afficher</button></div><button class="button" type="submit">Accéder à l’administration <x-ui-icon name="arrow-up-right" /></button><p id="login-feedback" role="status"></p><small>Simulation locale · Aucun compte réel créé</small></form></section>
@else
<section id="admin-gate" class="wrap section"><h2>Votre espace <em>professionnel.</em></h2><p>Explorez les outils du studio avec les identifiants de démonstration préremplis.</p><a class="button" href="{{ route('demo.pro', ['page'=>'connexion']) }}">Ouvrir la connexion <x-ui-icon name="arrow-up-right" /></a></section>
<div id="demo-admin" class="demo-admin" hidden><aside class="demo-sidebar"><p class="eyebrow">CANOPÉE / ADMINISTRATION</p><h2>Le studio.</h2><nav aria-label="Modules administrateur">@foreach(['overview'=>'Vue d’ensemble','content'=>'Contenus du site','requests'=>'Demandes & devis','clients'=>'Clients','appointments'=>'Rendez-vous','settings'=>'Réglages du studio'] as $key=>$label)<button type="button" data-admin-tab="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>@endforeach</nav><p class="storage-note">Données fictives conservées dans ce navigateur.</p><button type="button" id="demo-logout" data-login-url="{{ route('demo.pro', ['page'=>'connexion']) }}">Se déconnecter <x-ui-icon name="arrow-up-right" /></button></aside><section class="demo-workspace"><div class="admin-topbar"><span>ESPACE PROFESSIONNEL</span><span>AD · Administrateur démo</span></div><p id="business-feedback" role="status" aria-live="polite"></p><section data-admin-panel="overview"><p class="eyebrow">Votre activité, en un regard</p><h1>Bonjour,<br><em>le studio.</em></h1><p>Vos projets avancent. Voici les prochaines étapes.</p><div id="demo-metrics" class="demo-metrics"></div><div class="dashboard-columns"><article class="admin-card"><h2>Demandes récentes</h2><div id="recent-requests"></div></article><article class="admin-card"><h2>Rendez-vous à venir</h2><div id="next-appointments"></div></article></div></section>
<section data-admin-panel="content" hidden><p class="eyebrow">Publication & ligne éditoriale</p><h2>Vos contenus.</h2><div class="manager-toolbar"><div class="filters" role="group" aria-label="Collection à gérer"><button type="button" data-module="projects" aria-pressed="true">Réalisations</button><button type="button" data-module="articles" aria-pressed="false">Actualités</button></div><button class="button" id="new-entry" type="button">Ajouter un contenu +</button></div><p class="manager-feedback" role="status" aria-live="polite"></p><div id="manager-list"></div><button class="text-link reset-content" id="reset-content" type="button">Restaurer les contenus de démonstration</button><p class="storage-note">Les publications apparaissent sur les pages publiques de ce navigateur.</p></section>
<section data-admin-panel="requests" hidden><p class="eyebrow">Relation commerciale</p><h2>Demandes & devis.</h2><label for="request-search">Rechercher une demande</label><input id="request-search" type="search" placeholder="Nom ou projet…"><div id="request-list"></div></section>
<section data-admin-panel="clients" hidden><p class="eyebrow">Carnet de contacts</p><h2>Vos clients.</h2><form id="client-form" class="inline-form"><label>Nom<input name="name" required maxlength="80"></label><label>E-mail<input name="email" type="email" required maxlength="150"></label><button class="button" type="submit">Ajouter un client +</button></form><div id="client-list"></div></section>
<section data-admin-panel="appointments" hidden><p class="eyebrow">Organisation du studio</p><h2>Rendez-vous.</h2><form id="appointment-form" class="inline-form"><label>Objet<input name="title" required maxlength="100"></label><label>Date et heure<input name="date" type="datetime-local" required></label><button class="button" type="submit">Planifier +</button></form><div id="appointment-list"></div></section>
<section data-admin-panel="settings" hidden><p class="eyebrow">Identité & contact</p><h2>Réglages du studio.</h2><form id="business-settings" class="admin-card"><label>Nom du studio<input name="name" required maxlength="80"></label><label>E-mail de contact<input name="email" type="email" required maxlength="150"></label><label>Message du pied de page<textarea name="message" required maxlength="300" rows="3"></textarea></label><button class="button" type="submit">Enregistrer les réglages</button><p class="storage-note">Ces informations apparaissent dans le pied de page public.</p></form></section></section></div>

<dialog id="editor" aria-labelledby="editor-title"><form id="entry-form"><div class="editor-head"><h2 id="editor-title">Ajouter un contenu</h2><button type="button" id="close-editor" aria-label="Fermer l’éditeur"><x-ui-icon name="close" /></button></div><input type="hidden" id="entry-id"><label for="entry-title">Titre</label><input id="entry-title" required maxlength="100"><label for="entry-category">Catégorie</label><input id="entry-category" required maxlength="50"><label for="entry-text">Présentation</label><textarea id="entry-text" rows="5" required maxlength="2000"></textarea><label for="entry-image">Photographie d’ambiance</label><select id="entry-image"><option value="garden">Jardin</option><option value="interior">Espace de vie</option><option value="material">Matières naturelles</option></select><label for="entry-state">Visibilité</label><select id="entry-state"><option value="published">Publié</option><option value="draft">Brouillon</option></select><button class="button" type="submit">Enregistrer</button></form></dialog>
@endif
<noscript><p class="wrap section">Activez JavaScript pour découvrir les collections interactives et la gestion de démonstration.</p></noscript>
@if(in_array($page, ['accueil', 'studio', 'realisations', 'journal']))<section class="invitation"><img src="{{ asset('images/atelier/matiere.webp') }}" alt="" width="1000" height="750" loading="lazy"><div class="wrap" data-reveal><p class="eyebrow">Votre site, vos contenus</p><h2>Et si votre site<br><em>vivait avec vous ?</em></h2><a class="button" href="{{ route('demo.pro', ['page' => 'gestion']) }}">Essayer l’administration <x-ui-icon name="arrow-right" /></a></div></section>@endif
</main>
<footer class="footer"><div class="wrap"><div class="footer-grid"><p class="footer-claim">Des lieux qui ont du sens.</p><div><p class="eyebrow">Pages</p><ul>@foreach($pages as $slug => $label)<li><a href="{{ route('demo.pro', ['page' => $slug]) }}">{{ $label }}</a></li>@endforeach</ul></div><div><p class="eyebrow">Le studio</p><p id="studio-public-contact">Paysage & jardins vivants.<br>Un site pro imaginé par Codenyr.</p></div></div><p class="footer-word" aria-hidden="true" data-brand>canopée</p><div class="footer-bottom"><span>Marque et projets fictifs · Photos d’ambiance Unsplash</span><a href="{{ route('services') }}#pro">Retour à Codenyr <x-ui-icon name="arrow-up-right" /></a></div></div></footer>
<x-demo-tabbar :tabs="collect(['accueil' => ['Accueil', 'home'], 'studio' => ['Studio', 'user'], 'realisations' => ['Projets', 'grid'], 'journal' => ['Journal', 'book'], 'gestion' => ['Espace pro', 'lock']])->map(fn ($tab, $slug) => [route('demo.pro', ['page' => $slug]), $tab[0], $tab[1], $page === $slug || ($slug === 'gestion' && $page === 'connexion')])->values()->all()" />
@include('demos.studio')
</body></html>
