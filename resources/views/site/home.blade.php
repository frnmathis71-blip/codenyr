<x-site-layout>
<div class="home-page">
    @php
        $offers = app(\App\Services\PricingCatalog::class)->offers();
        $startingPrice = number_format(min(array_column($offers, 'amount_cents')) / 100, 0, ',', ' ');
    @endphp
    <section class="hero-split" aria-labelledby="home-title">
        <div class="hero-split-panel">
            <div>
                <p class="hero-split-kicker"><span aria-hidden="true"></span> Développeur web · Chalon-sur-Saône</p>
                <h1 id="home-title">Votre savoir-faire.<br>Un site <span>à sa hauteur.</span></h1>
                <p class="hero-split-lead">Des sites singuliers, des expériences simples. Je transforme vos idées en outils web qui présentent votre activité et vous aident à avancer.</p>
                <div class="hero-split-actions">
                    <a class="button" href="{{ route('quote') }}">Demander un devis <x-ui-icon name="arrow-up-right" /></a>
                    <a class="hero-split-link" href="#offres">Voir les tarifs <x-ui-icon name="arrow-down" /></a>
                </div>
            </div>
            <ul class="hero-split-facts">
                <li><strong>Dès {{ $startingPrice }} €</strong><span>Prix affichés</span></li>
                <li><strong>Devis gratuit</strong><span>Détaillé, sans engagement</span></li>
                <li><strong>1 interlocuteur</strong><span>Du début à la fin</span></li>
            </ul>
        </div>
        <div class="hero-split-mosaic">
            @foreach([
                ['demo.vitrine', 'atelier/interieur.jpg', 'Site vitrine', 'Atelier Rive', 'Intérieur lumineux du site de démonstration Atelier Rive'],
                ['demo.landing', 'sillage/coffee-moment.jpg', 'Landing page', 'Sillage', 'Préparation d’un café filtre, site de démonstration Sillage'],
                ['demo.pro', 'canopee/jardin.jpg', 'Site Pro', 'Canopée', 'Outils de jardinage, site de démonstration Canopée'],
            ] as [$route, $image, $type, $name, $alt])
                <a class="hero-tile" href="{{ route($route) }}">
                    @php $webp = preg_replace('/\.jpg$/', '', $image); @endphp
                    <img src="{{ asset('images/'.$webp.'-800.webp') }}" srcset="{{ asset('images/'.$webp.'-800.webp') }} 800w, {{ asset('images/'.$webp.'.webp') }} 1400w" sizes="{{ $loop->first ? '(max-width: 900px) 100vw, 50vw' : '(max-width: 900px) 50vw, 25vw' }}" width="800" height="533" alt="{{ $alt }}" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                    @if($loop->first)<span class="hero-tile-tag">Concepts Codenyr · marques fictives</span>@endif
                    <span class="hero-tile-label"><span><small>{{ $type }}</small><strong>{{ $name }}</strong></span><span class="hero-tile-arrow" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></span>
                </a>
            @endforeach
        </div>
    </section>

    @php $skills = ['Sites vitrines', 'Landing pages', 'Espaces d’administration', 'Réservation en ligne', 'Paiement Stripe', 'Comptes clients', 'SEO', 'Responsive', 'Sur mesure']; @endphp
    <div class="home-marquee" aria-label="Ce que je réalise : {{ implode(', ', $skills) }}">
        <div class="home-marquee-track" aria-hidden="true">
            @foreach([1, 2] as $copy)
                <div>@foreach($skills as $skill)<span>{{ $skill }}</span><i></i>@endforeach</div>
            @endforeach
        </div>
    </div>

    <section id="expertise" class="container home-section" aria-labelledby="expertise-title">
        <div class="home-section-heading"><div><p class="home-kicker">01 / L’exigence, jusque dans les détails</p><h2 id="expertise-title">Beau à regarder.<br><span>Simple à utiliser.</span></h2></div><p>Un bon site ne s’arrête pas à la première impression. Il doit être clair pour vos visiteurs et utile à votre activité.</p></div>
        <div class="home-bento">
            <article class="bento-tile bento-design">
                <div class="bento-specimen" aria-hidden="true">
                    <span class="bento-specimen-big">Aa</span>
                    <div class="bento-specimen-meta"><span>Typographie</span><span>Couleurs</span><span>Rythme</span></div>
                    <div class="bento-swatches"><i></i><i></i><i></i><i></i></div>
                </div>
                <div class="bento-copy"><span class="home-small-label">Design</span><h3>Une identité qui vous ressemble.</h3><p>Une direction visuelle sur mesure, une hiérarchie claire et le souci du détail pour faire ressortir votre savoir-faire.</p></div>
            </article>
            <article class="bento-tile bento-devices">
                <div class="home-art-devices" aria-hidden="true"><i></i><i></i></div>
                <div class="bento-copy"><span class="home-small-label">Expérience</span><h3>Le bon parcours, sur chaque écran.</h3></div>
            </article>
            <article class="bento-tile bento-admin">
                <div class="bento-toggles" aria-hidden="true"><span><i class="is-on"></i>Actualités</span><span><i class="is-on"></i>Réalisations</span><span><i></i>Brouillons</span></div>
                <div class="bento-copy"><span class="home-small-label">Autonomie</span><h3>Vos contenus, à portée de main.</h3></div>
            </article>
            <article class="bento-tile bento-code">
                <pre aria-hidden="true"><code><b>$site</b> = Codenyr::<em>pour</em>(<s>'votre activité'</s>)
    -><em>design</em>(<s>'sur mesure'</s>)
    -><em>responsive</em>()
    -><em>miseEnLigne</em>();</code></pre>
                <div class="bento-copy"><span class="home-small-label">Développement</span><h3>Des bases solides pour la suite.</h3><p>Un développement attentif aux performances et des fonctionnalités adaptées à vos besoins.</p></div>
            </article>
            <article class="bento-tile bento-local">
                <div class="bento-map" aria-hidden="true">
                    {{-- Simplified outline of mainland France, equirectangular projection; cities at their real coordinates. --}}
                    <svg viewBox="0 0 370 362" focusable="false">
                        <path class="bento-france" d="M203 10 253 52 297 72 363 92 347 148 328 156 306 196 330 216 319 248 330 284 344 300 336 304 300 328 284 320 264 316 245 312 220 352 223 356 184 352 138 344 88 316 96 308 105 268 105 228 88 192 77 168 55 152 17 140 6 116 28 104 55 100 83 108 94 108 94 64 105 76 140 76 176 48 182 20Z" />
                        <g class="bento-cities"><circle cx="202" cy="98" r="3" /><text x="190" y="102" text-anchor="end">Paris</text><circle cx="277" cy="159" r="3" /><text x="286" y="156">Dijon</text><circle cx="271" cy="222" r="3" /><text x="280" y="232">Lyon</text></g>
                        <circle class="bento-pin-ring" cx="271" cy="181" r="9" />
                        <circle class="bento-pin-dot" cx="271" cy="181" r="7" />
                    </svg>
                    <span class="bento-pin-label">Chalon-sur-Saône</span>
                </div>
                <div class="bento-copy"><span class="home-small-label">Proximité</span><h3>À vos côtés, où que vous soyez.</h3><p>Artisans, commerçants, indépendants, TPE et PME : échangeons en Bourgogne ou à distance.</p><a class="home-quiet-link" href="{{ route('about') }}">Rencontrer Codenyr <span aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a></div>
            </article>
        </div>
    </section>

    <section id="realisations" class="home-work-section" aria-labelledby="work-title">
        <div class="container">
            <div class="home-section-heading"><div><p class="home-kicker">02 / Du concret</p><h2 id="work-title">Différents univers.<br><span>La même attention.</span></h2></div><div><p>Une sélection pour découvrir mon approche du design et du développement.</p><a class="home-quiet-link" href="{{ route('projects') }}">Toutes les réalisations <span aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a></div></div>
            <div class="home-work-grid">
                @forelse($projects as $project)
                    <a class="home-work-card" href="{{ route('project', $project->slug) }}">
                        <div class="home-work-image">@if($project->imageUrl())<img src="{{ $project->imageUrl() }}" alt="Aperçu du projet {{ $project->name }}" loading="lazy" decoding="async" width="1000" height="680">@else<div class="home-work-placeholder">{{ $project->name }}</div>@endif<span class="home-work-open" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></div>
                        <div class="home-work-caption"><div><span class="home-small-label">{{ $project->category }}</span><h3>{{ $project->name }}</h3></div>@if($project->is_demo)<span class="home-demo-label">Concept de démonstration</span>@endif</div>
                        <p>{{ $project->short_description }}</p>
                    </a>
                @empty
                    <div class="empty-state"><h3>Votre projet pourrait être le prochain.</h3><p>Discutons de ce que nous pouvons construire ensemble.</p><a class="text-link" href="{{ route('quote') }}">Parler de mon projet <x-ui-icon name="arrow-right" /></a></div>
                @endforelse
            </div>
        </div>
    </section>

    <section id="offres" class="home-offers-section" aria-labelledby="offers-title">
        <div class="container">
            <div class="home-section-heading"><div><p class="home-kicker">03 / Un site pour chaque ambition</p><h2 id="offers-title">Des tarifs clairs.<br><span>Dès le départ.</span></h2></div><p>Commencer simplement ou aller plus loin. Quatre points de départ, un périmètre défini ensemble et un devis avant de commencer.</p></div>
            <x-pricing-cards />
            <div class="home-offers-footer"><p>Vous hésitez ? <a class="home-quiet-link" href="{{ route('services') }}#estimer">Estimez votre budget en une minute</a></p><a class="home-quiet-link" href="{{ route('services') }}#maintenance">Voir le détail et la maintenance <span aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a></div>
        </div>
    </section>

    <section id="methode" class="container home-section home-method" aria-labelledby="method-title">
        <div class="home-method-intro">
            <p class="home-kicker">04 / On avance ensemble</p>
            <h2 id="method-title">Un projet web.<br><span>Pas un saut dans l’inconnu.</span></h2>
            <p>Des étapes lisibles, des échanges directs et des décisions prises ensemble. Vous savez où nous allons.</p>
            <a class="button button-primary" href="{{ route('quote') }}">Commencer par un échange <span aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a>
        </div>
        <ol class="home-timeline">
            @foreach([['On fait connaissance','Votre activité, vos objectifs, vos envies. Je commence par comprendre ce qui compte pour vous.'],['On dessine le projet','Une proposition adaptée, un périmètre précis et un devis clair pour partir sur de bonnes bases.'],['Je donne vie aux idées','Design et développement, avec des points réguliers pour vous permettre de suivre et de valider.'],['Votre site prend son envol','Les dernières vérifications, la mise en ligne et la prise en main. La maintenance reste facultative.']] as [$name,$text])
                <li><span class="home-timeline-index">0{{ $loop->iteration }}</span><div><h3>{{ $name }}</h3><p>{{ $text }}</p></div></li>
            @endforeach
        </ol>
    </section>

    @if($testimonials->isNotEmpty())
        <section class="container home-section home-reviews" aria-labelledby="reviews-title">
            <div class="home-section-heading"><div><p class="home-kicker">Leur expérience</p><h2 id="reviews-title">La confiance se construit.</h2></div><a class="home-quiet-link" href="{{ route('reviews') }}">Tous les avis clients <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a></div>
            <div class="public-reviews-grid">@foreach($testimonials as $testimonial)<x-testimonial-card :testimonial="$testimonial" />@endforeach</div>
            <p class="home-reviews-note"><a href="{{ route('reviews') }}#fonctionnement-avis">Comment les avis sont recueillis</a></p>
        </section>
    @endif

    <section class="container home-faq" aria-labelledby="faq-title"><div><p class="home-kicker">Avant de se lancer</p><h2 id="faq-title">Les bonnes<br><span>questions.</span></h2><p>Un point à préciser ?<br><a class="home-quiet-link" href="{{ route('quote') }}">Demander un devis <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></a></p></div><div class="home-faq-list">
        @php
            $faq = [
            ['Je ne sais pas quel type de site choisir.', 'Aucun problème. Présentez-moi votre activité et ce que vous aimeriez accomplir. Nous définirons ensemble le type de site et le contenu adaptés à votre besoin.'],
            ['Que comprend le prix annoncé ?', 'Les prix affichés sont des points de départ. Chaque offre détaille son contenu sur la page Offres et tarifs. Le périmètre exact de votre projet est précisé dans un devis gratuit avant de commencer.'],
            ['Combien de temps faut-il pour créer mon site ?', 'En général deux semaines pour une landing page, trois à quatre semaines pour un site vitrine et environ six semaines pour un Site Pro, à compter de la réception de l’acompte et de vos contenus.'],
            ['Pourrai-je modifier mon site moi-même ?', 'L’offre Site Pro comprend un espace d’administration pour gérer vos contenus. Pour un projet sur mesure, les possibilités de gestion sont définies ensemble dans le devis.'],
            ['Le nom de domaine et l’hébergement sont-ils inclus ?', 'Ils sont souscrits à votre nom, pour que vous en restiez propriétaire, et réglés directement auprès du prestataire choisi. Je vous accompagne dans le choix et la mise en place.'],
            ['La maintenance est-elle obligatoire ?', 'Non. La maintenance est facultative et distincte des modifications ou nouvelles fonctionnalités. Nous pouvons en discuter selon l’accompagnement dont vous avez besoin.'],
        ];
        @endphp
        @foreach($faq as [$question,$answer])<details><summary>{{ $question }}<span aria-hidden="true">+</span></summary><p>{{ $answer }}</p></details>@endforeach
        @php
            // FAQ rich results; built in a raw PHP block because '@context' would otherwise be read as a Blade directive.
            $faqSchema = json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($item) => ['@type' => 'Question', 'name' => $item[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item[1]]], $faq)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        @endphp
        @push('head')<script type="application/ld+json">{!! $faqSchema !!}</script>@endpush
    </div></section>

    <section class="container home-final-cta"><div class="home-cta-orbit" aria-hidden="true"><span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></div><p class="home-kicker">VOTRE PROCHAINE ÉTAPE</p><h2>Vous avez le savoir-faire.<br>Faisons-le <span>rayonner.</span></h2><div class="home-final-bottom"><p>Une idée précise ou juste une envie d’avancer ?<br>Le premier échange est sans engagement.</p><a class="text-link" href="{{ route('quote') }}">Demander un devis <span aria-hidden="true"><span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></span></a></div></section>
</div>
</x-site-layout>
