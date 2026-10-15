<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f5f0e5">
    <title>Sillage — Du café, du caractère. Démonstration Codenyr</title>
    <meta name="description" content="Une landing page de démonstration imaginée par Codenyr pour une marque fictive de café de spécialité.">
    @fonts
    @vite('resources/js/demo-landing.js')
</head>
<body>
    <a class="skip-link" href="#contenu">Aller au contenu</a>
    <aside class="demo-bar" aria-label="À propos de cette démonstration"><span>Un concept imaginé par <strong>Codenyr</strong> · Marque fictive</span><a href="{{ route('services') }}#landing">Retour aux services <x-ui-icon name="arrow-up-right" /></a></aside>
    <header class="site-header wrap"><a href="#" class="wordmark" aria-label="Accueil"><b data-brand>sillage</b><span>®</span></a><nav aria-label="Navigation principale"><a href="#univers">Notre univers</a><a href="#cafes">Nos cafés</a><a href="#rituel">Le rituel</a><a href="#questions">Les réponses</a></nav><a class="small-cta" href="#cafes">Découvrir Sillage <span><x-ui-icon name="arrow-up-right" /></span></a></header>
    <main id="contenu">
        <section class="hero wrap">
            <div class="hero-copy"><p class="eyebrow"><span class="status-dot"></span> Café de spécialité · Esprit libre</p><h1>Du café.<br>Du caractère.<br><em>Du bon côté.</em></h1><p class="intro">Les grands départs commencent par une petite tasse. Découvrez un café qui réveille les sens et donne envie de prendre son temps.</p><a class="button dark" href="#cafes">Découvrez votre prochain rituel <span><span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></span></a><div class="hero-note">Du premier arôme à la dernière gorgée.</div></div>
            <figure class="hero-photo"><img src="{{ asset('images/sillage/coffee-moment.webp') }}" width="1200" height="800" alt="Préparation attentive d’un café filtre dans une carafe en verre" fetchpriority="high"><figcaption><span>LE GOÛT DES CHOSES BIEN FAITES</span><strong>Un geste simple.<br>Un moment à part.</strong></figcaption></figure>
            <span class="hero-badge" aria-hidden="true"><svg viewBox="0 0 120 120" focusable="false"><defs><path id="hero-badge-circle" d="M60 60m-45 0a45 45 0 1 1 90 0a45 45 0 1 1-90 0"/></defs><text><textPath href="#hero-badge-circle">CAFÉ DE SPÉCIALITÉ · TORRÉFIÉ AVEC SOIN · </textPath></text></svg><b>S</b></span>
        </section>
        <div class="values-strip"><div class="wrap"><span>Du grain à la tasse</span><span>Le goût avant tout</span><span>Les petits rituels font les grands jours</span></div></div>
        <section class="section wrap brand-story" id="univers"><div class="section-heading"><div><p class="eyebrow">Une certaine idée du café</p><h2>Bien plus qu’une tasse.<br><em>Une façon de vivre le quotidien.</em></h2></div><p>Sillage explore ce que le café laisse derrière lui :<br>un parfum, une conversation, un souvenir.</p></div><div class="story-columns"><article><span class="eyebrow">La curiosité</span><h3>Découvrir les nuances.</h3><p>Un café peut évoquer le cacao, les fruits ou les épices. Prendre le temps de sentir, puis de goûter, c’est découvrir une palette qui change selon les grains et leur préparation.</p></article><article><span class="eyebrow">Le geste</span><h3>Faire place à l’essentiel.</h3><p>Le bruit du moulin, l’eau qui frémit, les premiers arômes. La préparation devient un rituel simple, attentif, qui donne une autre saveur aux premières minutes de la journée.</p></article><article><span class="eyebrow">Le partage</span><h3>Créer des moments.</h3><p>Un café pris seul avant de partir ou partagé autour d’une table. Sillage raconte ces instants ordinaires auxquels on tient, et qui restent longtemps après la dernière gorgée.</p></article></div></section>
        <section class="selection wrap section" id="cafes">
            <div class="section-heading"><div><p class="eyebrow">01 — À chacun son caractère</p><h2>Trois expressions.<br><em>Une même passion.</em></h2></div><p>Trois personnalités. Une même passion.<br>Découvrez les nuances qui font leur singularité.</p></div>
            <div class="coffee-picker"><div class="product-art" data-roast="soleil" aria-hidden="true"><span class="product-art-top">LA COLLECTION SILLAGE</span><div class="mini-bag"><span>sillage.</span><b class="product-number">01</b><strong data-art-name>Le Soleil</strong><small>CAFÉ DE SPÉCIALITÉ · 250 G</small></div><span class="product-art-bottom">BON DÈS LE PREMIER BONJOUR.</span></div><div class="product-content"><div class="roast-options" role="group" aria-label="Choisir le caractère du café"><button type="button" data-coffee="soleil" aria-pressed="true">01 · Doux</button><button type="button" data-coffee="escale" aria-pressed="false">02 · Équilibré</button><button type="button" data-coffee="nocturne" aria-pressed="false">03 · Intense</button></div><div aria-live="polite" aria-atomic="true"><p class="eyebrow product-origin" data-origin>Brésil · Doux & généreux</p><h3 data-name>Le Soleil</h3><p class="product-description" data-description>Un café rond et réconfortant. Des notes de noisette et de chocolat au lait, pour commencer la journée du bon pied.</p><div class="flavor-notes" data-notes><span>Noisette</span><span>Chocolat au lait</span><span>Tout en rondeur</span></div></div><div class="tasting-story"><span class="eyebrow">À la dégustation</span><p data-tasting>Une texture enveloppante, une douceur familière et une finale gourmande. Le Soleil évoque les matins tranquilles et les grandes tablées.</p></div></div></div>
        </section>
        <section class="manifesto"><div class="wrap manifesto-grid"><figure class="origin-photo"><img src="{{ asset('images/sillage/coffee-beans.webp') }}" width="1400" height="1068" alt="Gros plan sur des grains de café torréfiés" loading="lazy" decoding="async"><figcaption>Le caractère commence avec le grain.</figcaption></figure><div><p class="eyebrow">Notre petite conviction</p><h2>La vie va vite.<br>Le bon café invite<br>à <em>ralentir.</em></h2><p>Un parfum qui remplit la cuisine. Une tasse entre les mains. Quelques minutes à soi. Chez Sillage, on aime le café pour son goût, mais surtout pour les moments qu’il crée.</p><span class="manifesto-sign">Un café. Et le reste peut attendre.</span></div></div></section>
        <section class="section wrap" id="rituel"><div class="section-heading"><div><p class="eyebrow">02 — Rien de compliqué</p><h2>Votre meilleur matin.<br><em>En trois temps.</em></h2></div><p>Une bonne tasse commence par quelques gestes simples.<br>Voici comment en faire votre rendez-vous préféré.</p></div><div class="steps"><article><span>01 / CHOISIR</span><h3>Suivez votre goût.</h3><p>Doux et gourmand ou intense et profond : le bon café, c’est d’abord celui qui vous plaît.</p></article><article><span>02 / PRÉPARER</span><h3>Faites à votre façon.</h3><p>Filtre, piston ou espresso. Choisissez une mouture adaptée à votre cafetière et laissez les arômes s’exprimer.</p></article><article><span>03 / SAVOURER</span><h3>Gardez ce moment.</h3><p>Votre tasse préférée, un peu de calme. Vous n’avez besoin de rien de plus pour bien commencer.</p></article></div></section>
        <section class="faq-section wrap" id="questions"><div><p class="eyebrow">03 — On vous répond</p><h2>Le café, oui.<br><em>Le flou, non.</em></h2></div><div class="questions"><details><summary>C’est quoi, un café de spécialité ?<span aria-hidden="true">+</span></summary><p>Un café dont on cherche à mettre en valeur l’origine et les arômes, avec une attention particulière à la sélection des grains et à la torréfaction. Ici, les références sont imaginées pour cette démonstration.</p></details><details><summary>En grains ou déjà moulu ?<span aria-hidden="true">+</span></summary><p>Choisissez les grains si vous avez un moulin. La mouture dépend ensuite de votre méthode : plus fine pour un espresso, plus grossière pour une cafetière à piston. Chaque préparation révèle une autre facette du café.</p></details><details><summary>Quelle est l’histoire de Sillage ?<span aria-hidden="true">+</span></summary><p>Sillage est une marque fictive imaginée par Codenyr autour d’une idée simple : raconter le café à travers les moments qu’il accompagne. Cette page illustre une présentation de marque, de son univers et de ses savoir-faire.</p></details></div></section>
        <section class="closing wrap"><span class="eyebrow">À demain matin.</span><h2>On se retrouve<br><em>autour d’un café ?</em></h2><a href="#cafes" class="button dark">Explorer notre univers <span><x-ui-icon name="arrow-up-right" /></span></a></section>
    </main>
    <footer class="wrap"><a class="wordmark" href="#"><b data-brand>sillage</b><span>®</span></a><p>Du café, du caractère.<br>Un concept de landing page signé Codenyr.</p><a href="{{ route('quote', ['offer' => 'Landing Page']) }}">Une page comme celle-ci pour votre activité ? <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></a><div class="footer-bottom"><span>Marque et collection fictives · Démonstration uniquement</span><a href="{{ route('services') }}#landing">Retour à Codenyr <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></a></div></footer>
    
    @include('demos.studio', ['studio' => [
        'key' => 'codenyr-sillage-preview-v2',
        'product' => 'Landing Page',
        'demo' => 'Sillage (landing page)',
        'brand' => 'sillage',
        'accent' => '--demo-accent',
        'palettes' => [
            'original' => ['Crème & terre cuite', '#f5f0e5', '#292b21', '#eb622d'],
            'forest' => ['Sauge & forêt', '#eef1e7', '#263e30', '#47693d'],
            'ocean' => ['Bleu porcelaine', '#edf2f6', '#203b52', '#32668c'],
            'night' => ['Nuit & cuivre', '#1d2427', '#f1e8d9', '#edb98f'],
            'berry' => ['Mûre & poudre', '#f7eef0', '#3d2230', '#a2445f'],
        ],
        'groups' => [
            'Typographie' => [
                'type' => ['Style des titres', ['original' => 'Signature — mixte', 'editorial' => 'Éditoriale — avec empattements', 'modern' => 'Contemporaine — sans empattements']],
                'scale' => ['Taille des titres', ['original' => 'Équilibrée', 'large' => 'Affirmée', 'small' => 'Discrète']],
            ],
            'Mise en page' => [
                'layout' => ['Ouverture de la page', ['original' => 'Texte et photo côte à côte', 'reverse' => 'Photo à gauche', 'centered' => 'Grand visuel, texte centré']],
                'nav' => ['En-tête', ['original' => 'Classique', 'sticky' => 'Fixe au défilement']],
                'width' => ['Largeur du contenu', ['original' => 'Large', 'narrow' => 'Resserrée']],
                'density' => ['Espacement des sections', ['original' => 'Aéré', 'compact' => 'Compact']],
            ],
            'Détails' => [
                'decor' => ['Décor', ['rich' => 'Riche — formes et profondeur', 'flat' => 'Sobre — à plat']],
                'corners' => ['Forme des éléments', ['original' => 'Lignes droites', 'soft' => 'Courbes douces']],
                'buttons' => ['Boutons', ['original' => 'Pleins', 'pill' => 'Arrondis', 'outline' => 'Contours']],
                'photos' => ['Traitement des photos', ['original' => 'Naturel', 'warm' => 'Chaleureux', 'mono' => 'Noir et blanc']],
            ],
        ],
        'presets' => [
            'original' => ['Signature', 'Le design d’origine', ['palette' => 'original', 'type' => 'original', 'scale' => 'original', 'layout' => 'original', 'nav' => 'original', 'corners' => 'original', 'buttons' => 'original', 'photos' => 'original']],
            'nature' => ['Naturel', 'Végétal, doux et chaleureux', ['palette' => 'forest', 'type' => 'editorial', 'scale' => 'original', 'layout' => 'reverse', 'nav' => 'original', 'corners' => 'soft', 'buttons' => 'pill', 'photos' => 'warm']],
            'minimal' => ['Minimal', 'Épuré, centré, contemporain', ['palette' => 'ocean', 'type' => 'modern', 'scale' => 'small', 'layout' => 'centered', 'nav' => 'sticky', 'corners' => 'original', 'buttons' => 'outline', 'photos' => 'original']],
            'premium' => ['Premium', 'Sombre, élégant, affirmé', ['palette' => 'night', 'type' => 'editorial', 'scale' => 'large', 'layout' => 'original', 'nav' => 'sticky', 'corners' => 'original', 'buttons' => 'original', 'photos' => 'mono']],
        ],
        'defaults' => ['decor' => 'rich', 'palette' => 'original', 'type' => 'original', 'scale' => 'original', 'layout' => 'original', 'nav' => 'original', 'width' => 'original', 'density' => 'original', 'corners' => 'original', 'buttons' => 'original', 'photos' => 'original'],
    ]])
</body>
</html>
