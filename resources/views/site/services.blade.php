<x-site-layout title="Offres et tarifs de création de sites internet" description="Landing page, site vitrine, site administrable ou sur mesure à Chalon-sur-Saône : offres et tarifs dès 490 €, maintenance facultative.">
<section class="container page-hero page-hero-center">
    <p class="eyebrow">Offres et tarifs</p>
    <h1>Votre activité. Votre site.<br><span class="muted-heading">Des prix clairs.</span></h1>
    <p>Quatre points de départ, un devis détaillé et sans engagement.<br>Vous savez ce que vous payez avant de commencer.</p>
    <nav class="page-jump" aria-label="Sur cette page"><a href="#tarifs">Les offres</a><a href="#estimer">Estimer mon budget</a><a href="#choisir">Comment choisir</a><a href="#maintenance">Maintenance</a></nav>
</section>

<section id="tarifs" class="container section pricing-section" aria-label="Offres de création">
    <x-pricing-cards anchors />
    <p class="fine-print">Prix indicatifs de départ. Le périmètre, les délais, l’hébergement et le régime de TVA applicable seront précisés sur votre devis. Aucune formule n’est présélectionnée.</p>
</section>

<section id="estimer" class="container section estimator-section" aria-labelledby="estimate-title">
    <div class="section-heading"><div><p class="eyebrow"><span>02 /</span> Estimer mon budget</p><h2 id="estimate-title">Votre prix en une minute.</h2></div><p class="section-intro">Quatre questions, une fourchette immédiate. Envoyez-la ensuite en un clic pour recevoir un devis précis.</p></div>
    <x-price-estimator />
</section>

<section id="choisir" class="container section offer-guide-section" aria-labelledby="choose-title">
    <div class="section-heading"><div><p class="eyebrow"><span>03 /</span> Comment choisir</p><h2 id="choose-title">Partez de votre besoin.</h2></div><p class="section-intro">Pas sûr de la formule ? Repérez la situation qui vous ressemble, nous ajusterons ensemble dans le devis.</p></div>
    <div class="offer-guide">
        @php($situations = ['landing' => 'Vous lancez une offre ou une activité', 'vitrine' => 'Vous voulez présenter votre entreprise', 'pro' => 'Vous voulez publier vous-même', 'custom' => 'Vous avez un besoin métier précis'])
        @foreach(app(\App\Services\PricingCatalog::class)->offers() as $key => $offer)
            <a class="offer-guide-item" href="#{{ $key }}">
                <span class="offer-guide-situation">{{ $situations[$key] ?? '' }}</span>
                <strong>{{ $offer['name'] }} <x-ui-icon name="arrow-up" /></strong>
                <p>{{ $offer['description'] }}</p>
            </a>
        @endforeach
    </div>
</section>

<section id="maintenance" class="section secondary-section" aria-labelledby="maintenance-title">
    <div class="container">
        <div class="section-heading"><div><p class="eyebrow"><span>04 /</span> Et après la mise en ligne ?</p><h2 id="maintenance-title">La maintenance. Si vous le souhaitez.</h2></div><p class="section-intro">Vous recevez votre site clé en main et pouvez gérer votre propre hébergement. L’accompagnement Codenyr est facultatif.</p></div>
        <div class="maintenance-grid">
            @foreach(app(\App\Services\PricingCatalog::class)->maintenance() as $plan)
                <article class="maintenance-card">
                    <h3>{{ $plan['name'] }}</h3>
                    <p class="maintenance-price"><small class="maintenance-from">{{ $plan['starting_from'] ? 'À partir de' : 'Forfait mensuel' }}</small><strong>{{ $plan['price'] }} €</strong><small>/mois</small></p>
                    <p>{{ $plan['intro'] }}</p>
                    <a class="text-link" href="{{ route('contact') }}">En discuter <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a>
                </article>
            @endforeach
        </div>
        <div class="notice"><strong>Maintenance ≠ modification ≠ nouvelle fonctionnalité.</strong><p>La maintenance concerne le bon fonctionnement du site. Les changements de contenu, de design et les nouvelles fonctionnalités font l’objet d’un chiffrage distinct. Le détail des prestations est défini dans votre contrat.</p></div>
    </div>
</section>
<x-cta />
</x-site-layout>
