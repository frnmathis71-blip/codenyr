<x-site-layout title="Offres et tarifs de création de sites internet" description="Landing page, site vitrine, site administrable ou sur mesure à Chalon-sur-Saône : offres et tarifs dès 390 €, maintenance facultative.">
<section class="container page-hero page-hero-center">
    <p class="eyebrow">Offres et tarifs</p>
    <h1>Votre activité. Votre site.<br><span class="muted-heading">Des prix clairs.</span></h1>
    <p>Quatre points de départ, un devis détaillé et sans engagement.<br>Vous savez ce que vous payez avant de commencer.</p>
    <nav class="page-jump" aria-label="Sur cette page"><a href="#tarifs">Les offres</a><a href="#maintenance">Maintenance</a><a href="#choisir">Comment choisir</a></nav>
</section>

<section id="tarifs" class="container section pricing-section" aria-label="Offres de création et maintenance">
    <x-pricing-cards anchors maintenance />
    <p class="fine-print">Prix indicatifs de départ. Le périmètre, les délais, l’hébergement et le régime de TVA applicable seront précisés sur votre devis. Aucune formule n’est présélectionnée. Maintenance facultative, sans engagement et résiliable chaque mois.</p>
    <div class="notice"><strong>Maintenance ≠ modification ≠ nouvelle fonctionnalité.</strong><p>La maintenance concerne le bon fonctionnement du site. Les changements de contenu, de design et les nouvelles fonctionnalités font l’objet d’un chiffrage distinct. Le détail des prestations est défini dans votre contrat.</p></div>
</section>

<section id="choisir" class="container section offer-guide-section" aria-labelledby="choose-title">
    <div class="section-heading"><div><p class="eyebrow"><span>02 /</span> Comment choisir</p><h2 id="choose-title">Partez de votre besoin.</h2></div><p class="section-intro">Pas sûr de la formule ? Repérez la situation qui vous ressemble, nous ajusterons ensemble dans le devis.</p></div>
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
<x-cta />
</x-site-layout>
