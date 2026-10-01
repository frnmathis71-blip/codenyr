<x-site-layout>
<div class="home-page">
    <section class="container home-hero" aria-labelledby="home-title">
        <div class="home-hero-copy">
            <p class="home-kicker"><span aria-hidden="true"></span> Développement web indépendant · Chalon-sur-Saône</p>
            <h1 id="home-title">Votre savoir-faire.<br>Un site <span>à sa hauteur.</span></h1>
            <p class="home-lead">Des sites singuliers, des expériences simples. Je transforme vos idées en outils web qui présentent votre activité et vous aident à avancer.</p>
            <div class="home-hero-actions"><a class="button button-primary" href="{{ route('quote') }}">Demander un devis <span aria-hidden="true"><span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></span></a><a class="home-quiet-link" href="{{ route('projects') }}">Explorer les réalisations <span aria-hidden="true"><x-ui-icon name="arrow-down" /></span></a></div>
            <div class="home-signature"><span class="home-signature-mark" aria-hidden="true">C<span><span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></span></span><p><strong>Une relation directe, du début à la fin.</strong><br>Un développeur, un projet pensé avec vous.</p></div>
        </div>
        <x-home-preview />
        <div class="home-hero-bottom"><span>DESIGN SOIGNÉ. DÉVELOPPEMENT SUR MESURE.</span><a href="{{ route('services') }}">Découvrir les services <span aria-hidden="true"><x-ui-icon name="arrow-down-right" /></span></a></div>
    </section>

    <section id="expertise" class="home-expertise" aria-labelledby="expertise-title">
        <div class="container">
            <div class="home-section-heading"><div><p class="home-kicker">01 / L’exigence, jusque dans les détails</p><h2 id="expertise-title">Beau à regarder.<br><span>Simple à utiliser.</span></h2></div><p>Un bon site ne s’arrête pas à la première impression. Il doit être clair pour vos visiteurs et utile à votre activité.</p></div>
            <div class="home-principles">
                <article><div class="home-principle-art" aria-hidden="true"><span class="home-art-corners">Aa<span><x-ui-icon name="arrow-up-right" /></span></span></div><span class="home-small-label">01 — DESIGN</span><h3>Une identité qui vous ressemble.</h3><p>Une direction visuelle sur mesure, une hiérarchie claire et le souci du détail pour faire ressortir votre savoir-faire.</p></article>
                <article><div class="home-principle-art" aria-hidden="true"><span class="home-art-devices"><i></i><i></i></span></div><span class="home-small-label">02 — EXPÉRIENCE</span><h3>Le bon parcours, sur chaque écran.</h3><p>Des contenus lisibles, une navigation intuitive et des actions évidentes, du téléphone à l’ordinateur.</p></article>
                <article><div class="home-principle-art" aria-hidden="true"><span class="home-art-code">&lt;<i>/</i>&gt;</span></div><span class="home-small-label">03 — DÉVELOPPEMENT</span><h3>Des bases solides pour la suite.</h3><p>Un développement attentif aux performances et des fonctionnalités adaptées à vos besoins, avec une administration selon l’offre choisie.</p></article>
            </div>
        </div>
    </section>

    <section id="realisations" class="container home-section" aria-labelledby="work-title">
        <div class="home-section-heading"><div><p class="home-kicker">02 / Du concret</p><h2 id="work-title">Différents univers.<br><span>La même attention.</span></h2></div><div><p>Une sélection pour découvrir mon approche du design et du développement.</p><a class="home-quiet-link" href="{{ route('projects') }}">Toutes les réalisations <span aria-hidden="true"><span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></span></a></div></div>
        <div class="home-work-grid">
            @forelse($projects as $project)
                <a class="home-work-card" href="{{ route('project', $project->slug) }}">
                    <div class="home-work-image">@if($project->imageUrl())<img src="{{ $project->imageUrl() }}" alt="Aperçu du projet {{ $project->name }}" loading="lazy" decoding="async" width="1000" height="680">@else<div class="home-work-placeholder">{{ $project->name }}</div>@endif<span class="home-work-open" aria-hidden="true"><span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></span></div>
                    <div class="home-work-caption"><div><span class="home-small-label">{{ $project->category }}</span><h3>{{ $project->name }}</h3></div>@if($project->is_demo)<span class="home-demo-label">Concept de démonstration</span>@endif</div>
                    <p>{{ $project->short_description }}</p>
                </a>
            @empty
                <div class="empty-state"><h3>Votre projet pourrait être le prochain.</h3><p>Discutons de ce que nous pouvons construire ensemble.</p><a class="text-link" href="{{ route('quote') }}">Parler de mon projet <x-ui-icon name="arrow-right" /></a></div>
            @endforelse
        </div>
    </section>

    <section id="offres" class="home-offers-section" aria-labelledby="offers-title">
        <div class="container">
            <div class="home-section-heading"><div><p class="home-kicker">03 / Un site pour chaque ambition</p><h2 id="offers-title">Votre besoin donne<br>le ton.</h2></div><p>Commencer simplement ou aller plus loin. Quatre points de départ, un périmètre défini ensemble et un devis avant de commencer.</p></div>
            <div class="home-offers">
                @foreach(app(\App\Services\PricingCatalog::class)->offers() as $key => $offer)
                    <a class="home-offer" href="{{ route('services').'#'.$key }}">
                        <span class="home-offer-number">0{{ $loop->iteration }}</span><div class="home-offer-name"><span>{{ ['landing'=>'POUR LANCER UNE IDÉE','vitrine'=>'POUR PRÉSENTER VOTRE ACTIVITÉ','pro'=>'POUR GARDER LA MAIN','custom'=>'POUR VOS BESOINS MÉTIER'][$key] }}</span><h3>{{ $offer['name'] }}</h3></div><p>{{ $offer['intro'] }}</p><div class="home-offer-price"><span>À partir de</span><strong>{{ $offer['price'] }} <small>€</small></strong></div><span class="home-offer-arrow" aria-hidden="true"><span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></span>
                    </a>
                @endforeach
            </div>
            <div class="home-offers-footer"><p>Vous hésitez ? Nous trouverons ensemble le bon point de départ.</p><a href="{{ route('pricing') }}">Comparer le contenu des offres <span aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a></div>
        </div>
    </section>

    <section id="methode" class="container home-section" aria-labelledby="method-title">
        <div class="home-section-heading"><div><p class="home-kicker">04 / On avance ensemble</p><h2 id="method-title">Un projet web.<br><span>Pas un saut dans l’inconnu.</span></h2></div><p>Des étapes lisibles, des échanges directs et des décisions prises ensemble. Vous savez où nous allons.</p></div>
        <ol class="home-process">
            @foreach([['On fait connaissance','Votre activité, vos objectifs, vos envies. Je commence par comprendre ce qui compte pour vous.'],['On dessine le projet','Une proposition adaptée, un périmètre précis et un devis clair pour partir sur de bonnes bases.'],['Je donne vie aux idées','Design et développement, avec des points réguliers pour vous permettre de suivre et de valider.'],['Votre site prend son envol','Les dernières vérifications, la mise en ligne et la prise en main. La maintenance reste facultative.']] as [$name,$text])
                <li><span class="home-step-index">0{{ $loop->iteration }}</span><h3>{{ $name }}</h3><p>{{ $text }}</p></li>
            @endforeach
        </ol>
        <div class="home-local-note"><span aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span><p><strong>Basé à Chalon-sur-Saône. À vos côtés, où que vous soyez.</strong><br>Artisans, commerçants, indépendants, TPE et PME : échangeons en Bourgogne ou à distance.</p><a class="home-quiet-link" href="{{ route('about') }}">Rencontrer Codenyr <span aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a></div>
    </section>

    @if($testimonials->isNotEmpty())
        <section class="container home-section home-reviews" aria-labelledby="reviews-title">
            <div class="home-section-heading"><div><p class="home-kicker">Leur expérience</p><h2 id="reviews-title">La confiance se construit.</h2></div><a class="home-quiet-link" href="{{ route('reviews') }}">Tous les avis clients <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a></div>
            <div class="public-reviews-grid">@foreach($testimonials as $testimonial)<x-testimonial-card :testimonial="$testimonial" />@endforeach</div>
        </section>
    @endif

    <section class="container home-faq" aria-labelledby="faq-title"><div><p class="home-kicker">Avant de se lancer</p><h2 id="faq-title">Les bonnes<br><span>questions.</span></h2><p>Un point à préciser ?<br><a class="home-quiet-link" href="{{ route('quote') }}">Demander un devis <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></a></p></div><div class="home-faq-list">
        @foreach([['Je ne sais pas quel type de site choisir.','Aucun problème. Présentez-moi votre activité et ce que vous aimeriez accomplir. Nous définirons ensemble le type de site et le contenu adaptés à votre besoin.'],['Que comprend le prix annoncé ?','Les prix affichés sont des points de départ. Chaque offre possède un contenu détaillé sur la page Tarifs. Le périmètre exact de votre projet est précisé dans un devis avant de commencer.'],['Pourrai-je modifier mon site moi-même ?','L’offre Site Pro comprend un espace d’administration pour gérer vos contenus. Pour un projet sur mesure, les possibilités de gestion sont définies ensemble dans le devis.'],['La maintenance est-elle obligatoire ?','Non. La maintenance est facultative et distincte des modifications ou nouvelles fonctionnalités. Nous pouvons en discuter selon l’accompagnement dont vous avez besoin.']] as [$question,$answer])<details><summary>{{ $question }}<span aria-hidden="true">+</span></summary><p>{{ $answer }}</p></details>@endforeach
    </div></section>

    <section class="container home-final-cta"><div class="home-cta-orbit" aria-hidden="true"><span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></div><p class="home-kicker">VOTRE PROCHAINE ÉTAPE</p><h2>Vous avez le savoir-faire.<br>Faisons-le <span>rayonner.</span></h2><div class="home-final-bottom"><p>Une idée précise ou juste une envie d’avancer ?<br>Le premier échange est sans engagement.</p><a class="text-link" href="{{ route('quote') }}">Demander un devis <span aria-hidden="true"><span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></span></a></div></section>
</div>
</x-site-layout>
