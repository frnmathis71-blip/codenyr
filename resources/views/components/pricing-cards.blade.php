@props(['featured' => 'vitrine', 'anchors' => false])
@php
    $audiences = ['landing' => 'Pour lancer une idée', 'vitrine' => 'Pour présenter votre activité', 'pro' => 'Pour garder la main', 'custom' => 'Pour vos besoins métier'];
    $demos = ['landing' => 'demo.landing', 'vitrine' => 'demo.vitrine', 'pro' => 'demo.pro'];
@endphp
<div class="price-grid">
    @foreach(app(\App\Services\PricingCatalog::class)->offers() as $key => $offer)
        <article @if($anchors) id="{{ $key }}" @endif @class(['price-card', 'is-featured' => $key === $featured]) aria-labelledby="offer-{{ $key }}">
            @if($key === $featured)<span class="price-badge">Recommandé</span>@endif
            <header>
                <span class="price-audience">{{ $audiences[$key] ?? '' }}</span>
                <h3 id="offer-{{ $key }}">{{ $offer['name'] }}</h3>
                <p>{{ $offer['intro'] }}</p>
            </header>
            <p class="price-amount"><small>À partir de</small><strong>{{ $offer['price'] }}<span>€</span></strong></p>
            <a @class(['button', 'button-primary' => $key === $featured, 'button-secondary' => $key !== $featured]) href="{{ route('quote', ['offer' => $offer['name']]) }}">Demander un devis</a>
            <ul class="price-features" aria-label="Inclus dans l’offre {{ $offer['name'] }}">
                @foreach($offer['features'] as $feature)
                    <li><x-ui-icon name="check" />{{ $feature }}</li>
                @endforeach
            </ul>
            @isset($demos[$key])
                <a class="price-demo" href="{{ route($demos[$key]) }}">Voir un exemple <x-ui-icon name="arrow-up-right" /></a>
            @endisset
        </article>
    @endforeach
</div>
<ul class="price-reassurance">
    <li><x-ui-icon name="check" />Devis détaillé et gratuit</li>
    <li><x-ui-icon name="check" />Périmètre et prix fixés avant de commencer</li>
    <li><x-ui-icon name="check" />Maintenance facultative</li>
</ul>
