@php
    $pages = ['accueil' => 'Accueil', 'atelier' => 'L’atelier', 'expertises' => 'Expertises', 'realisations' => 'Réalisations', 'contact' => 'Contact'];
    $projects = [['Maison Lumière', 'Habitat', 'interieur.webp', 'Des volumes ouverts, des teintes minérales et une lumière qui accompagne les heures.'], ['La Parenthèse', 'Rénovation', 'maison.webp', 'Repenser les circulations et révéler le caractère d’un intérieur.'], ['Épure', 'Aménagement', 'matiere.webp', 'Le bois, la pierre et des lignes simples composent un lieu apaisé.']];
    $services = [['Architecture intérieure', 'Repenser les volumes, les perspectives et les circulations pour faire émerger un espace harmonieux.', 'Organisation des espaces · Ambiances · Choix des matières'], ['Rénovation', 'Révéler les qualités d’un lieu existant, conserver ses singularités et l’adapter à de nouveaux usages.', 'Lecture de l’existant · Intentions de transformation · Cohérence du projet'], ['Aménagement', 'Composer un intérieur dans lequel chaque élément trouve sa place, du mobilier à la lumière.', 'Mobilier · Couleurs · Éclairage · Détails']];
    $pageImages = ['atelier' => 'matiere.webp', 'expertises' => 'maison.webp', 'realisations' => 'interieur.webp'];
    $studio = [
        'key' => 'codenyr-atelier-preview-v3',
        'product' => 'Site Vitrine',
        'demo' => 'Atelier Rive (site vitrine)',
        'brand' => 'atelier rive',
        'accent' => '--accent',
        'palettes' => [
            'mineral' => ['Minéral & argile', '#f5f2eb', '#302f29', '#805743'],
            'olive' => ['Olive & lin', '#f1f2e9', '#303d2e', '#526a3c'],
            'blue' => ['Bleu architecte', '#edf2f5', '#233d50', '#346b90'],
            'rose' => ['Rose poudré', '#faf0ed', '#4c3038', '#965765'],
            'dark' => ['Obsidienne', '#202625', '#f1eee5', '#dcc3a0'],
        ],
        'groups' => [
            'Typographie' => [
                'type' => ['Style des titres', ['signature' => 'Signature éditoriale', 'modern' => 'Contemporaine', 'classic' => 'Classique']],
                'scale' => ['Taille des titres', ['normal' => 'Équilibrée', 'large' => 'Affirmée', 'small' => 'Discrète']],
            ],
            'Mise en page' => [
                'layout' => ['Présentation d’accueil', ['full' => 'Photo plein écran', 'framed' => 'Photo encadrée', 'split' => 'Deux colonnes']],
                'nav' => ['Navigation', ['burger' => 'Menu plein écran', 'bar' => 'Barre horizontale']],
                'cards' => ['Présentation des projets', ['grid' => 'Grille', 'list' => 'Liste éditoriale', 'mosaic' => 'Mosaïque']],
                'width' => ['Largeur du contenu', ['large' => 'Large', 'narrow' => 'Resserrée']],
                'density' => ['Espacement', ['airy' => 'Généreux', 'compact' => 'Compact']],
            ],
            'Détails' => [
                'decor' => ['Décor', ['rich' => 'Vivant — animations et grain', 'flat' => 'Sobre — sans effet']],
                'corners' => ['Formes', ['square' => 'Angles droits', 'rounded' => 'Arrondis']],
                'buttons' => ['Boutons', ['solid' => 'Pleins', 'outline' => 'Contours', 'pill' => 'Arrondis']],
                'photos' => ['Traitement des photos', ['natural' => 'Naturel', 'warm' => 'Chaleureux', 'mono' => 'Noir et blanc']],
            ],
        ],
        'presets' => [
            'signature' => ['Signature', 'Grande photo et menu plein écran', ['palette' => 'mineral', 'type' => 'signature', 'scale' => 'normal', 'layout' => 'full', 'nav' => 'burger', 'cards' => 'grid', 'corners' => 'square', 'buttons' => 'solid', 'photos' => 'natural']],
            'gallery' => ['Galerie', 'Les images au premier plan', ['palette' => 'mineral', 'type' => 'classic', 'scale' => 'large', 'layout' => 'framed', 'nav' => 'bar', 'cards' => 'mosaic', 'corners' => 'square', 'buttons' => 'outline', 'photos' => 'natural']],
            'soft' => ['Douceur', 'Arrondi, clair et chaleureux', ['palette' => 'rose', 'type' => 'signature', 'scale' => 'normal', 'layout' => 'split', 'nav' => 'bar', 'cards' => 'grid', 'corners' => 'rounded', 'buttons' => 'pill', 'photos' => 'warm']],
            'nocturne' => ['Nocturne', 'Sombre et haut de gamme', ['palette' => 'dark', 'type' => 'classic', 'scale' => 'large', 'layout' => 'full', 'nav' => 'burger', 'cards' => 'list', 'corners' => 'square', 'buttons' => 'outline', 'photos' => 'mono']],
        ],
        'defaults' => ['decor' => 'rich', 'palette' => 'mineral', 'type' => 'signature', 'scale' => 'normal', 'layout' => 'full', 'nav' => 'burger', 'cards' => 'grid', 'width' => 'large', 'density' => 'airy', 'corners' => 'square', 'buttons' => 'solid', 'photos' => 'natural'],
    ];
@endphp
<!DOCTYPE html><html lang="fr" @foreach($studio['defaults'] as $setting => $value) data-{{ $setting }}="{{ $value }}" @endforeach><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $pages[$page] }} — Atelier Rive · Démonstration Codenyr</title>@fonts @vite('resources/js/demo-atelier.js')</head><body class="page-{{ $page }}">
<a class="skip" href="#main">Aller au contenu</a>
<div class="demo-banner"><span>ATELIER RIVE · Concept fictif par Codenyr</span><a href="{{ route('services') }}#vitrine">Retour aux services <x-ui-icon name="arrow-up-right" /></a></div>
<header class="header">
    <button class="burger" type="button" aria-controls="navigation" aria-expanded="false" hidden><span class="burger-lines" aria-hidden="true"><i></i><i></i><i></i></span><span class="burger-label">Menu</span></button>
    <a class="brand" href="{{ route('demo.vitrine') }}"><b data-brand>atelier rive</b><span>Architecture & intérieurs</span></a>
    <a class="header-cta" href="{{ route('demo.vitrine', ['page' => 'contact']) }}">Prendre rendez-vous</a>
    <nav id="navigation" class="menu" aria-label="Navigation principale">
        <ol>@foreach($pages as $slug => $label)<li><a href="{{ route('demo.vitrine', ['page' => $slug]) }}" @if($page === $slug) aria-current="page" @endif><small>0{{ $loop->iteration }}</small>{{ $label }}</a></li>@endforeach</ol>
        <div class="menu-aside"><p class="eyebrow">L’atelier</p><p>Architecture intérieure, rénovation et aménagement de lieux de vie.</p><p class="eyebrow">Rencontres</p><p>Sur rendez-vous, à l’atelier ou chez vous.</p><a class="button" href="{{ route('demo.vitrine', ['page' => 'contact']) }}">Parler de votre projet</a></div>
    </nav>
</header>
<main id="main">
@if($page === 'accueil')
<section class="hero">
    <figure class="hero-media"><img src="{{ asset('images/atelier/interieur.webp') }}" alt="Salon lumineux aux matières naturelles" width="1400" height="934" fetchpriority="high"></figure>
    <div class="hero-content">
        <p class="eyebrow">Des lieux à vivre, naturellement.</p>
        <h1>L’espace juste.<br><em>La vie autour.</em></h1>
        <div class="hero-bottom">
            <a class="hero-cta" href="{{ route('demo.vitrine', ['page' => 'realisations']) }}"><span><small>Projets de concept</small>Explorer nos réalisations</span><x-ui-icon name="arrow-right" /></a>
            <a class="scroll-cue" href="#suite"><span>Découvrir</span><x-ui-icon name="arrow-down" /></a>
        </div>
    </div>
</section>
<section id="suite" class="wrap intro section">
    <p class="eyebrow" data-reveal>Une vision sensible de l’habitat</p>
    <div data-reveal><h2 class="statement">Nous imaginons l’architecture à partir de ceux qui l’habitent. Les usages donnent le rythme, la lumière trace les lignes, <em>les matières installent la douceur.</em></h2><a class="text-link" href="{{ route('demo.vitrine', ['page' => 'atelier']) }}">Rencontrer l’univers de l’atelier <x-ui-icon name="arrow-right" /></a></div>
</section>
<div class="marquee" aria-hidden="true"><div>@for($i = 0; $i < 2; $i++)<span>Architecture intérieure</span><span>Rénovation</span><span>Aménagement</span><span>Lumière</span><span>Matières</span>@endfor</div></div>
<section class="wrap section">
    <div class="section-head" data-reveal><div><p class="eyebrow">Sélection</p><h2>Fragments <em>de lieux.</em></h2></div><a class="text-link" href="{{ route('demo.vitrine', ['page' => 'realisations']) }}">Tous les projets <x-ui-icon name="arrow-right" /></a></div>
    <div class="projects">@foreach($projects as $project)<a class="project" data-reveal href="{{ route('demo.vitrine', ['page' => 'realisations']) }}#projet-{{ $loop->index }}"><div class="project-media"><img src="{{ asset('images/atelier/'.$project[2]) }}" alt="{{ $project[0] }}, inspiration d’intérieur" loading="lazy" width="1000" height="750"></div><div class="project-body"><span class="eyebrow">{{ $project[1] }}</span><h3>{{ $project[0] }}</h3><p>{{ $project[3] }}</p></div></a>@endforeach</div>
</section>
<section class="wrap section expertise-list">
    <div class="section-head" data-reveal><div><p class="eyebrow">Expertises</p><h2>Trois façons <em>d’intervenir.</em></h2></div></div>
    @foreach($services as $service)<a class="expertise-row" data-reveal href="{{ route('demo.vitrine', ['page' => 'expertises']) }}"><span class="num">0{{ $loop->iteration }}</span><h3>{{ $service[0] }}</h3><p>{{ $service[1] }}</p><x-ui-icon name="arrow-up-right" /></a>@endforeach
</section>
@elseif($page === 'atelier')
<section class="wrap page-head"><p class="eyebrow">L’atelier / Notre regard</p><h1>Habiter autrement.<br><em>Commencer par vous.</em></h1><p class="lead">Un atelier imaginé autour de l’écoute, de la simplicité et du plaisir de vivre dans un espace qui nous ressemble.</p></section>
<figure class="wrap page-banner"><img src="{{ asset('images/atelier/'.$pageImages[$page]) }}" alt="Intérieur aux matières et tons naturels" width="1000" height="750"></figure>
<section class="wrap editorial section"><p class="eyebrow" data-reveal>Notre philosophie</p><div data-reveal><h2>Moins de superflu.<br><em>Plus de sens.</em></h2><p>Avant les plans viennent les conversations. Comment un lieu est-il vécu ? Qu’aimerait-on y changer ? Quels détails méritent d’être conservés ?</p><p>Cette approche privilégie des espaces lisibles, des matières expressives et des choix cohérents. Le projet se construit dans le dialogue, du premier croquis aux derniers détails.</p></div></section>
<section class="wrap section pillars"><article data-reveal><span>01 / Écouter</span><h3>Comprendre les usages.</h3><p>Mettre les habitudes et les envies au cœur de la réflexion.</p></article><article data-reveal><span>02 / Révéler</span><h3>Respecter l’existant.</h3><p>Identifier ce qui fait le caractère du lieu et lui donner sa place.</p></article><article data-reveal><span>03 / Équilibrer</span><h3>Dessiner l’essentiel.</h3><p>Relier fonctionnalité, lumière et cohérence des matières.</p></article></section>
@elseif($page === 'expertises')
<section class="wrap page-head"><p class="eyebrow">Expertises / Du regard au détail</p><h1>Chaque projet,<br><em>sa juste réponse.</em></h1><p class="lead">Une approche à différentes échelles, de la transformation d’un lieu à l’aménagement de ses espaces.</p></section>
<figure class="wrap page-banner"><img src="{{ asset('images/atelier/'.$pageImages[$page]) }}" alt="Façade et intérieur rénovés" width="1000" height="750"></figure>
<section class="wrap section services">@foreach($services as $service)<article data-reveal><span class="num">0{{ $loop->iteration }}</span><div><h2>{{ $service[0] }}</h2><p>{{ $service[1] }}</p><small>{{ $service[2] }}</small></div></article>@endforeach</section>
<section class="wrap process section"><div class="section-head" data-reveal><div><p class="eyebrow">Le parcours d’un projet</p><h2>De l’idée <em>au lieu.</em></h2></div></div><div class="pillars"><article data-reveal><span>Étape 01</span><h3>La rencontre</h3><p>Explorer le contexte, les usages et les aspirations.</p></article><article data-reveal><span>Étape 02</span><h3>La conception</h3><p>Donner forme aux idées avec des plans et des intentions de matières.</p></article><article data-reveal><span>Étape 03</span><h3>Les détails</h3><p>Affiner les choix pour un ensemble cohérent.</p></article></div></section>
@elseif($page === 'realisations')
<section class="wrap page-head"><p class="eyebrow">Réalisations / Carnet d’inspirations</p><h1>Des espaces.<br><em>Des façons de vivre.</em></h1><p class="lead">Trois projets de concept pour explorer notre univers. Les photographies sont des illustrations d’ambiance, pas des réalisations d’une agence réelle.</p></section>
<section class="wrap section"><div class="filters" role="group" aria-label="Filtrer les projets">@foreach(['Tous','Habitat','Rénovation','Aménagement'] as $filter)<button type="button" data-filter="{{ $filter }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $filter }}</button>@endforeach</div><div class="projects">@foreach($projects as $project)<article class="project" data-reveal id="projet-{{ $loop->index }}" data-category="{{ $project[1] }}"><div class="project-media"><img src="{{ asset('images/atelier/'.$project[2]) }}" alt="{{ $project[0] }}, illustration d’ambiance" width="1000" height="750" loading="lazy"></div><div class="project-body"><span class="eyebrow">{{ $project[1] }} · Concept 0{{ $loop->iteration }}</span><h2>{{ $project[0] }}</h2><p>{{ $project[3] }}</p><details><summary>Découvrir l’intention du projet</summary><p>Un travail sur la continuité des espaces, les contrastes de textures et la place de la lumière naturelle. Une inspiration pour des intérieurs simples, chaleureux et agréables à vivre.</p></details></div></article>@endforeach</div><p class="filter-status" role="status" aria-live="polite"></p></section>
@else
<section class="wrap page-head"><p class="eyebrow">Contact / Premier échange</p><h1>Tout commence<br><em>par une conversation.</em></h1><p class="lead">Présenter un projet, partager une envie, poser une question. Voici un exemple de formulaire de prise de contact.</p></section>
<section class="wrap contact-grid section"><div><h2>Parlons <em>de votre lieu.</em></h2><p>La première rencontre permet de préciser vos besoins et de comprendre ce qui compte pour vous.</p><div class="contact-note"><span class="eyebrow">Démonstration</span><p>Atelier Rive est une marque fictive. Ce formulaire illustre l’expérience de contact : aucune information n’est envoyée ni enregistrée.</p></div><a class="text-link" href="{{ route('contact') }}">Contacter réellement Codenyr <x-ui-icon name="arrow-right" /></a></div><form id="demo-contact"><label for="name">Votre nom</label><input id="name" name="name" autocomplete="name" required maxlength="100"><label for="email">Votre adresse e-mail</label><input id="email" name="email" type="email" autocomplete="email" required maxlength="200"><label for="project-type">Votre projet</label><select id="project-type" name="project"><option>Architecture intérieure</option><option>Rénovation</option><option>Aménagement</option></select><label for="message">Quelques mots sur votre projet</label><textarea id="message" name="message" rows="4" required maxlength="3000"></textarea><button class="button" type="submit" disabled>Tester le formulaire <x-ui-icon name="arrow-right" /></button><noscript>Activez JavaScript pour essayer le formulaire.</noscript><p id="form-result" role="status" aria-live="polite"></p></form></section>
@endif
@if($page !== 'contact')<section class="invitation"><img src="{{ asset('images/atelier/matiere.webp') }}" alt="" width="1000" height="750" loading="lazy"><div class="wrap" data-reveal><p class="eyebrow">Une nouvelle perspective</p><h2>Et si l’on imaginait<br><em>la suite ensemble ?</em></h2><a class="button" href="{{ route('demo.vitrine', ['page' => 'contact']) }}">Faire le premier pas <x-ui-icon name="arrow-right" /></a></div></section>@endif
</main>
<footer class="footer"><div class="wrap"><div class="footer-grid"><p class="footer-claim">Des lieux qui ont du sens.</p><div><p class="eyebrow">Pages</p><ul>@foreach($pages as $slug => $label)<li><a href="{{ route('demo.vitrine', ['page' => $slug]) }}">{{ $label }}</a></li>@endforeach</ul></div><div><p class="eyebrow">L’atelier</p><p>Architecture intérieure & lieux de vie.<br>Un site vitrine imaginé par Codenyr.</p></div></div><p class="footer-word" aria-hidden="true" data-brand>atelier rive</p><div class="footer-bottom"><span>Marque et projets fictifs · Photos d’ambiance Unsplash</span><a href="{{ route('services') }}#vitrine">Retour à Codenyr <x-ui-icon name="arrow-up-right" /></a></div></div></footer>
@include('demos.studio')
</body></html>
