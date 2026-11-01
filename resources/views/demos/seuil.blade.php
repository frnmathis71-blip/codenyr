@php
    $pages = ['accueil' => 'Accueil', 'biens' => 'Nos biens', 'visite' => 'Réserver une visite', 'estimation' => 'Estimation', 'espace' => 'Espace client', 'agence' => 'Espace agence'];
    $titles = $pages + ['bien' => 'Fiche du bien'];
    $url = fn (string $page, array $query = []) => route('demo.custom', ['page' => $page] + $query);
    $studio = [
        'key' => 'codenyr-seuil-preview-v1',
        'product' => 'Sur mesure',
        'demo' => 'Seuil Immobilier (site sur mesure)',
        'brand' => 'seuil',
        'accent' => '--accent',
        'palettes' => [
            'mineral' => ['Laiton & ardoise', '#f4f1ec', '#1f2a33', '#a8743a'],
            'blue' => ['Bleu architecte', '#edf2f5', '#233d50', '#346b90'],
            'olive' => ['Olive & lin', '#f1f2e9', '#303d2e', '#526a3c'],
            'rose' => ['Rose poudré', '#faf0ed', '#4c3038', '#965765'],
            'dark' => ['Obsidienne', '#202625', '#f1eee5', '#dcc3a0'],
        ],
        'groups' => [
            'Typographie' => [
                'type' => ['Style des titres', ['modern' => 'Contemporaine', 'signature' => 'Signature éditoriale', 'classic' => 'Classique']],
                'scale' => ['Taille des titres', ['normal' => 'Équilibrée', 'large' => 'Affirmée', 'small' => 'Discrète']],
            ],
            'Mise en page' => [
                'layout' => ['Présentation d’accueil', ['full' => 'Photo plein écran', 'framed' => 'Photo encadrée', 'split' => 'Deux colonnes']],
                'nav' => ['Navigation', ['burger' => 'Menu déroulant', 'bar' => 'Barre horizontale']],
                'cards' => ['Présentation des biens', ['grid' => 'Grille', 'list' => 'Liste détaillée', 'mosaic' => 'Mosaïque']],
                'width' => ['Largeur du contenu', ['large' => 'Large', 'narrow' => 'Resserrée']],
                'density' => ['Espacement', ['airy' => 'Généreux', 'compact' => 'Compact']],
            ],
            'Détails' => [
                'decor' => ['Décor', ['rich' => 'Vivant — animations et grain', 'flat' => 'Sobre — sans effet']],
                'corners' => ['Formes', ['rounded' => 'Arrondis', 'square' => 'Angles droits']],
                'buttons' => ['Boutons', ['solid' => 'Pleins', 'outline' => 'Contours', 'pill' => 'Arrondis']],
                'photos' => ['Traitement des photos', ['natural' => 'Naturel', 'warm' => 'Chaleureux', 'mono' => 'Noir et blanc']],
            ],
        ],
        'presets' => [
            'seuil' => ['Seuil', 'Laiton, grande photo, menu discret', ['palette' => 'mineral', 'type' => 'modern', 'scale' => 'normal', 'layout' => 'full', 'nav' => 'burger', 'cards' => 'grid', 'corners' => 'rounded', 'buttons' => 'solid', 'photos' => 'natural']],
            'prestige' => ['Prestige', 'Sombre et haut de gamme', ['palette' => 'dark', 'type' => 'classic', 'scale' => 'large', 'layout' => 'full', 'nav' => 'burger', 'cards' => 'list', 'corners' => 'square', 'buttons' => 'outline', 'photos' => 'warm']],
            'clair' => ['Clair', 'Bleu, net et structuré', ['palette' => 'blue', 'type' => 'modern', 'scale' => 'small', 'layout' => 'framed', 'nav' => 'bar', 'cards' => 'grid', 'corners' => 'square', 'buttons' => 'solid', 'photos' => 'natural']],
            'maison' => ['Maison', 'Doux, chaleureux, éditorial', ['palette' => 'olive', 'type' => 'signature', 'scale' => 'normal', 'layout' => 'split', 'nav' => 'bar', 'cards' => 'mosaic', 'corners' => 'rounded', 'buttons' => 'pill', 'photos' => 'warm']],
        ],
        'defaults' => ['decor' => 'rich', 'palette' => 'mineral', 'type' => 'modern', 'scale' => 'normal', 'layout' => 'full', 'nav' => 'burger', 'cards' => 'grid', 'width' => 'large', 'density' => 'airy', 'corners' => 'rounded', 'buttons' => 'solid', 'photos' => 'natural'],
    ];
@endphp
<!DOCTYPE html><html lang="fr" @foreach($studio['defaults'] as $setting => $value) data-{{ $setting }}="{{ $value }}" @endforeach><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $titles[$page] }} — Seuil Immobilier · Démonstration Codenyr</title><script>document.documentElement.classList.add('js')</script>@fonts @vite('resources/js/demo-immo.js')</head><body class="page-{{ $page }}" data-urls="{{ json_encode(['bien' => $url('bien'), 'visite' => $url('visite'), 'espace' => $url('espace'), 'biens' => $url('biens')]) }}">
<a class="skip" href="#main">Aller au contenu</a>
<div class="demo-banner"><span>SEUIL IMMOBILIER · Concept fictif par Codenyr</span><a href="{{ route('services') }}#custom">Retour aux services <x-ui-icon name="arrow-up-right" /></a></div>
<header class="header">
    <button class="burger" type="button" aria-controls="navigation" aria-expanded="false" hidden><span class="burger-lines" aria-hidden="true"><i></i><i></i><i></i></span><span class="burger-label">Menu</span></button>
    <a class="brand" href="{{ $url('accueil') }}"><b data-brand>seuil</b><span>Agence immobilière</span></a>
    <a class="header-cta" href="{{ $url('visite') }}">Réserver une visite</a>
    <nav id="navigation" class="menu" aria-label="Navigation principale"><ol>@foreach($pages as $slug => $label)<li><a href="{{ $url($slug) }}" @if($page === $slug || ($page === 'bien' && $slug === 'biens')) aria-current="page" @endif>{{ $label }}</a></li>@endforeach</ol></nav>
</header>

<main id="main">
@if($page === 'accueil')
<section class="hero">
    <figure class="hero-media"><img src="{{ asset('images/atelier/maison.webp') }}" alt="Pièce de vie lumineuse d’une maison" width="1000" height="750" fetchpriority="high"></figure>
    <div class="hero-content">
        <p class="eyebrow">Agence immobilière · Achat, vente, location</p>
        <h1>Trouver le lieu.<br><em>Y entrer sereinement.</em></h1>
        <div class="hero-bottom">
            <a class="hero-cta" href="{{ $url('biens') }}"><span><small>Sélection du moment</small>Découvrir nos biens</span><x-ui-icon name="arrow-right" /></a>
            <a class="scroll-cue" href="#recherche"><span>Découvrir</span><x-ui-icon name="arrow-down" /></a>
        </div>
    </div>
</section>
<section id="recherche" class="wrap section estate-search-section">
    <form class="estate-search" action="{{ $url('biens') }}" method="get" data-reveal>
        <p class="eyebrow">Que cherchez-vous ?</p>
        <label>Projet<select name="mode"><option value="Vente">Acheter</option><option value="Location">Louer</option></select></label>
        <label>Type de bien<select name="type"><option value="">Tous</option><option>Maison</option><option>Appartement</option><option>Loft</option></select></label>
        <label>Budget maximum<input name="budget" type="number" min="0" step="1000" inputmode="numeric" placeholder="Sans limite"></label>
        <button class="button" type="submit">Rechercher <x-ui-icon name="arrow-right" /></button>
    </form>
</section>
<section class="wrap section">
    <div class="section-head" data-reveal><div><p class="eyebrow">À la une</p><h2>Des lieux <em>à visiter.</em></h2></div><a class="text-link" href="{{ $url('biens') }}">Tous les biens <x-ui-icon name="arrow-right" /></a></div>
    <div class="projects" data-estate="featured"></div>
</section>
<section class="wrap section expertise-list">
    <div class="section-head" data-reveal><div><p class="eyebrow">Nos services</p><h2>Un projet, <em>un interlocuteur.</em></h2></div></div>
    @foreach([['Acheter', 'Des biens sélectionnés, des visites réservées en ligne et un dossier qui se complète au fil du projet.', $url('biens')], ['Vendre', 'Une estimation argumentée, une mise en valeur soignée et des visites qualifiées.', $url('estimation')], ['Louer', 'Un dossier locataire en ligne, complet avant même la première visite.', $url('biens', ['mode' => 'Location'])]] as [$name, $text, $href])<a class="expertise-row" data-reveal href="{{ $href }}"><span class="num">0{{ $loop->iteration }}</span><h3>{{ $name }}</h3><p>{{ $text }}</p><x-ui-icon name="arrow-up-right" /></a>@endforeach
</section>
<section class="wrap section pillars"><article data-reveal><span>01 / Réserver</span><h3>Une visite en trois clics.</h3><p>Choisissez un bien, un créneau libre, et recevez la confirmation immédiatement.</p></article><article data-reveal><span>02 / Constituer</span><h3>Un dossier qui avance.</h3><p>Déposez vos justificatifs depuis votre espace client et suivez ce qu’il reste à fournir.</p></article><article data-reveal><span>03 / Signer</span><h3>Accompagné jusqu’au bout.</h3><p>Votre conseiller suit chaque étape, de l’offre à la remise des clés.</p></article></section>
@elseif($page === 'biens')
<section class="wrap page-head"><p class="eyebrow">Nos biens / Achat et location</p><h1>Des adresses.<br><em>Des façons de vivre.</em></h1><p class="lead">Filtrez les biens disponibles et réservez une visite directement en ligne. Les annonces sont fictives, les photographies sont des illustrations d’ambiance.</p></section>
<section class="wrap section">
    <form class="estate-filters" id="estate-filters">
        <div class="filters" role="group" aria-label="Projet">@foreach(['Vente' => 'Acheter', 'Location' => 'Louer'] as $mode => $label)<button type="button" data-mode="{{ $mode }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>@endforeach</div>
        <label>Type<select name="type"><option value="">Tous les types</option><option>Maison</option><option>Appartement</option><option>Loft</option></select></label>
        <label>Budget maximum<input name="budget" type="number" min="0" step="1000" inputmode="numeric" placeholder="Sans limite"></label>
        <label>Pièces minimum<select name="rooms"><option value="0">Indifférent</option><option value="2">2 et plus</option><option value="3">3 et plus</option><option value="4">4 et plus</option><option value="5">5 et plus</option></select></label>
    </form>
    <p class="estate-count" role="status" aria-live="polite"></p>
    <div class="projects" data-estate="list"></div>
</section>
@elseif($page === 'bien')
<section class="wrap estate-detail" id="estate-detail">
    <a class="text-link estate-back" href="{{ $url('biens') }}"><x-ui-icon name="arrow-left" /> Retour aux biens</a>
    <h1 data-detail-title>Fiche du bien</h1>
    <div data-detail-body><p class="lead">Chargement de l’annonce…</p></div>
    <noscript><p class="lead">Activez JavaScript pour afficher l’annonce.</p></noscript>
</section>
@elseif($page === 'visite')
<section class="wrap page-head"><p class="eyebrow">Visite / Réservation en ligne</p><h1>Réservez votre visite.<br><em>À l’heure qui vous va.</em></h1><p class="lead">Choisissez un bien et un créneau libre. Votre dossier client est créé en même temps : vous le retrouvez dans votre espace.</p></section>
<section class="wrap section">
    <form class="booking" id="booking-form" novalidate>
        <fieldset class="booking-step"><legend><span>01</span> Le bien</legend><label for="booking-property">Bien à visiter</label><select id="booking-property" name="property" required></select><div class="booking-property-card" data-booking-property></div></fieldset>
        <fieldset class="booking-step"><legend><span>02</span> Le créneau</legend>
            <div class="slot-toolbar"><button type="button" class="slot-nav" data-week="-1" aria-label="Semaine précédente"><x-ui-icon name="arrow-left" /></button><p data-week-label role="status" aria-live="polite"></p><button type="button" class="slot-nav" data-week="1" aria-label="Semaine suivante"><x-ui-icon name="arrow-right" /></button></div>
            <div class="slot-week" data-slot-week></div>
            <p class="slot-choice" data-slot-choice>Aucun créneau choisi.</p>
        </fieldset>
        <fieldset class="booking-step"><legend><span>03</span> Vos coordonnées</legend>
            <div class="form-grid">
                <label>Nom et prénom<input name="name" required maxlength="80" autocomplete="name"></label>
                <label>Adresse e-mail<input name="email" type="email" required maxlength="150" autocomplete="email"></label>
                <label>Téléphone<input name="phone" type="tel" maxlength="20" autocomplete="tel"></label>
                <label>Votre projet<select name="project"><option>Achat</option><option>Location</option><option>Investissement</option></select></label>
            </div>
            <label class="check"><input type="checkbox" name="dossier" checked> Créer mon dossier client pour suivre ma recherche</label>
        </fieldset>
        <p class="form-error" data-booking-error role="alert"></p>
        <button class="button" type="submit">Confirmer la visite <x-ui-icon name="arrow-right" /></button>
        <p class="demo-note">Démonstration : la réservation est enregistrée dans ce navigateur uniquement. Aucun message n’est envoyé.</p>
    </form>
    <div class="result-card" data-booking-result hidden tabindex="-1"></div>
</section>
@elseif($page === 'estimation')
<section class="wrap page-head"><p class="eyebrow">Estimation / Vous vendez</p><h1>Combien vaut<br><em>votre bien ?</em></h1><p class="lead">Une première fourchette immédiate, puis une estimation précise sur place par un conseiller. Votre dossier vendeur est ouvert automatiquement.</p></section>
<section class="wrap section estimate-grid">
    <form class="form-card" id="estimate-form">
        <div class="form-grid">
            <label>Type de bien<select name="type"><option>Maison</option><option>Appartement</option><option>Loft</option></select></label>
            <label>Quartier<select name="area"><option>Centre historique</option><option>Bords de rivière</option><option>Quartier des Jardins</option><option>Faubourg</option><option>Quartier de la gare</option></select></label>
            <label>Surface habitable (m²)<input name="surface" type="number" min="10" max="1000" required inputmode="numeric"></label>
            <label>Nombre de pièces<input name="rooms" type="number" min="1" max="20" value="4" required inputmode="numeric"></label>
            <label>État général<select name="state"><option value="1.08">Rénové récemment</option><option value="1" selected>Bon état</option><option value="0.88">Travaux à prévoir</option></select></label>
            <label>Extérieur<select name="outside"><option value="1">Aucun</option><option value="1.05">Balcon ou terrasse</option><option value="1.1">Jardin</option></select></label>
            <label>Nom et prénom<input name="name" required maxlength="80" autocomplete="name"></label>
            <label>Adresse e-mail<input name="email" type="email" required maxlength="150" autocomplete="email"></label>
        </div>
        <button class="button" type="submit">Obtenir mon estimation <x-ui-icon name="arrow-right" /></button>
        <p class="demo-note">Démonstration : les prix au m² sont fictifs et la fourchette est indicative.</p>
    </form>
    <div class="result-card" data-estimate-result hidden tabindex="-1"></div>
</section>
@elseif($page === 'espace')
<section class="wrap page-head"><p class="eyebrow">Espace client / Votre dossier</p><h1>Votre espace client.</h1><p class="lead">Suivez vos visites, déposez vos justificatifs et voyez en un coup d’œil ce qu’il reste à fournir.</p></section>
<section class="wrap section">
    <form class="form-card login-card" id="client-login">
        <h2>Accéder à mon dossier</h2>
        <label>Adresse e-mail<input name="email" type="email" required value="camille@seuil.demo" autocomplete="off"></label>
        <button class="button" type="submit">Ouvrir mon espace <x-ui-icon name="arrow-right" /></button>
        <p class="form-error" role="alert"></p>
        <p class="demo-note">Compte de démonstration prérempli. Un dossier créé lors d’une réservation fonctionne aussi.</p>
    </form>
    <div class="client-space" id="client-space" hidden></div>
</section>
@else
<section class="wrap page-head"><p class="eyebrow">Espace agence / Gestion</p><h1>L’agence, <em>en un regard.</em></h1><p class="lead">Agenda des visites, dossiers clients, annonces et créneaux d’ouverture : tout l’outil interne de l’agence, avec des données fictives modifiables.</p></section>
<section class="wrap section">
    <form class="form-card login-card" id="agency-login">
        <h2>Connexion de l’équipe</h2>
        <label>Adresse e-mail<input name="email" type="email" required value="agence@seuil.demo" autocomplete="off"></label>
        <label>Mot de passe<input name="password" type="password" required value="Seuil2026!" autocomplete="off"></label>
        <button class="button" type="submit">Se connecter <x-ui-icon name="arrow-right" /></button>
        <p class="form-error" role="alert"></p>
        <p class="demo-note">Identifiants fictifs préremplis. Simulation locale : rien n’est envoyé.</p>
    </form>
    <div class="agency" id="agency" hidden>
        <aside class="agency-nav"><p class="eyebrow">Seuil / Agence</p><nav aria-label="Modules de l’agence">@foreach(['overview' => 'Vue d’ensemble', 'agenda' => 'Agenda des visites', 'clients' => 'Dossiers clients', 'listings' => 'Annonces', 'slots' => 'Créneaux d’ouverture'] as $key => $label)<button type="button" data-agency-tab="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>@endforeach</nav><button type="button" class="text-link" data-agency-reset>Restaurer les données de démonstration</button><button type="button" class="text-link" data-agency-logout>Se déconnecter</button></aside>
        <div class="agency-panel"><p class="agency-feedback" role="status" aria-live="polite"></p><div data-agency-content></div></div>
    </div>
</section>
@endif
<noscript><p class="wrap section">Activez JavaScript pour découvrir les biens, la réservation et les espaces de démonstration.</p></noscript>
@if(in_array($page, ['accueil', 'biens', 'bien']))<section class="invitation"><img src="{{ asset('images/atelier/interieur.webp') }}" alt="" width="1000" height="750" loading="lazy"><div class="wrap" data-reveal><p class="eyebrow">Vous vendez ?</p><h2>Estimez votre bien<br><em>en deux minutes.</em></h2><a class="button" href="{{ $url('estimation') }}">Lancer l’estimation <x-ui-icon name="arrow-right" /></a></div></section>@endif
</main>
<footer class="footer"><div class="wrap"><div class="footer-grid"><p class="footer-claim">Trouver le lieu. Y entrer sereinement.</p><div><p class="eyebrow">Pages</p><ul>@foreach($pages as $slug => $label)<li><a href="{{ $url($slug) }}">{{ $label }}</a></li>@endforeach</ul></div><div><p class="eyebrow">L’agence</p><p>Achat, vente, location et gestion.<br>Un site sur mesure imaginé par Codenyr.</p></div></div><p class="footer-word" aria-hidden="true" data-brand>seuil</p><div class="footer-bottom"><span>Agence, biens et clients fictifs · Photos d’ambiance Unsplash</span><a href="{{ route('services') }}#custom">Retour à Codenyr <x-ui-icon name="arrow-up-right" /></a></div></div></footer>
<x-demo-tabbar :tabs="collect(['accueil' => ['Accueil', 'home'], 'biens' => ['Biens', 'grid'], 'visite' => ['Visite', 'clock'], 'estimation' => ['Estimer', 'tag'], 'espace' => ['Mon espace', 'user']])->map(fn ($tab, $slug) => [$url($slug), $tab[0], $tab[1], $page === $slug || ($slug === 'biens' && $page === 'bien')])->values()->all()" />
@include('demos.studio')
</body></html>
