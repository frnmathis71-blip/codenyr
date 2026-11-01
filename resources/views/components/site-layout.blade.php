@props(['title' => 'Création de sites internet à Chalon-sur-Saône', 'description' => 'Création de sites internet sur mesure à Chalon-sur-Saône pour artisans, commerçants et TPE. Design soigné, prix affichés dès 390 €, accompagnement local.', 'image' => null, 'noindex' => false])
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Codenyr</title>
    <meta name="description" content="{{ $description }}">
    @if($noindex || request()->routeIs('customer.*'))<meta name="robots" content="noindex,nofollow">@endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:title" content="{{ $title }} — Codenyr">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $image ?? asset('images/social-preview.png') }}">
    <meta property="og:site_name" content="Codenyr">
    @unless($image)<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">@endunless
    <meta property="og:image:alt" content="Codenyr — Création de sites internet sur mesure">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }} — Codenyr">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image ?? asset('images/social-preview.png') }}">
    <meta name="theme-color" content="#0D1117">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon-48.png') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('favicon-96.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=codenyr">
    <meta name="apple-mobile-web-app-title" content="Codenyr">
    @fonts
    @vite(['resources/css/site.css', 'resources/js/site.js'])
    @livewireStyles
    @php
        // Local business card for search engines, built from the same data as the legal notice.
        preg_match('/^(.*?),\s*(\d{5})\s+([^,]+)/u', (string) config('codenyr.legal.address'), $postal);
        $organization = array_filter([
            '@context' => 'https://schema.org', '@type' => 'ProfessionalService', 'name' => 'Codenyr', 'url' => url('/'),
            'logo' => asset('images/codenyr.png'), 'image' => asset('images/social-preview.png'), 'email' => config('codenyr.email'),
            'telephone' => config('codenyr.legal.phone') ? '+33'.ltrim(preg_replace('/\D/', '', config('codenyr.legal.phone')), '0') : null,
            'founder' => ['@type' => 'Person', 'name' => config('codenyr.legal.owner')],
            'priceRange' => 'À partir de '.number_format(min(array_column(app(\App\Services\PricingCatalog::class)->offers(), 'amount_cents')) / 100, 0, ',', ' ').' €',
            'address' => $postal ? ['@type' => 'PostalAddress', 'streetAddress' => $postal[1], 'postalCode' => $postal[2], 'addressLocality' => trim($postal[3]), 'addressCountry' => 'FR'] : null,
            'areaServed' => [['@type' => 'City', 'name' => 'Chalon-sur-Saône'], ['@type' => 'AdministrativeArea', 'name' => 'Saône-et-Loire'], ['@type' => 'Country', 'name' => 'France']],
            'sameAs' => array_values(config('codenyr.social')) ?: null,
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @if(request()->routeIs('home'))
    @php
        // Site name shown by Google above the result instead of the bare domain.
        $website = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Codenyr', 'url' => url('/')];
    @endphp
    <script type="application/ld+json">{!! json_encode($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endif
    @stack('head')
</head>
<body class="site-body">
    <a class="skip-link" href="#main">Aller au contenu</a>
    @if(request()->routeIs('home'))
    {{-- Homepage: transparent over the hero, links slide out beside the menu button. --}}
    <header class="site-header home-header" x-data="{ open: false, scrolled: window.scrollY > 40 }" @scroll.window="scrolled = window.scrollY > 40" :class="{ 'menu-open': open, 'is-scrolled': scrolled }" @click.outside="open = false" @keydown.escape.window="if (open) { open = false; $refs.menuButton.focus() }">
        <button type="button" x-ref="menuButton" class="home-burger" @click="open = !open" :aria-expanded="open" aria-expanded="false" aria-controls="home-menu"><span class="home-burger-lines" aria-hidden="true"><i></i><i></i><i></i></span><span class="home-burger-label">Menu</span></button>
        <a href="{{ route('home') }}" aria-label="Codenyr, accueil" class="brand"><img src="{{ asset('images/codenyr-logo.webp') }}" width="660" height="220" alt="Codenyr"></a>
        <div class="home-header-actions">
            @auth
                <a class="account-link" href="{{ route('dashboard') }}">{{ auth()->user()->is_admin ? 'Administration' : 'Mon espace' }}</a>
            @else
                <a class="account-link" href="{{ route('login') }}">Connexion</a>
            @endauth
            <a class="home-header-cta" href="{{ route('quote') }}">Demander un devis</a>
        </div>
        <nav id="home-menu" class="home-menu" aria-label="Navigation principale" @click="if ($event.target.closest('a')) open = false">
            <ol>
                @foreach(['home' => 'Accueil', 'services' => 'Offres et tarifs', 'projects' => 'Réalisations', 'reviews' => 'Avis clients', 'about' => 'À propos', 'contact' => 'Contact'] as $route => $label)<li><a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a></li>@endforeach
                @auth<li class="home-menu-account"><a href="{{ route('dashboard') }}">{{ auth()->user()->is_admin ? 'Administration' : 'Mon espace client' }}</a></li>@else<li class="home-menu-account"><a href="{{ route('login') }}">Connexion</a></li>@endauth
            </ol>
        </nav>
    </header>
    @else
    <header class="site-header" x-data="{ open: false }" @keydown.escape.window="if (open) { open = false; $refs.menuButton.focus() }">
        <div class="container header-inner">
            <a href="{{ route('home') }}" aria-label="Codenyr, accueil" class="brand"><img src="{{ asset('images/codenyr-logo.webp') }}" width="660" height="220" alt="Codenyr"></a>
            <nav class="desktop-nav" aria-label="Navigation principale">
                @foreach(['home' => 'Accueil', 'services' => 'Offres et tarifs', 'projects' => 'Réalisations', 'reviews' => 'Avis clients', 'about' => 'À propos', 'contact' => 'Contact'] as $route => $label)
                    <a href="{{ route($route) }}" @class(['active' => request()->routeIs($route)]) @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="header-account-actions">
                @auth
                    <a class="account-link" href="{{ route('dashboard') }}">{{ auth()->user()->is_admin ? 'Administration' : 'Mon espace' }}</a>
                @else
                    <a class="account-link" href="{{ route('login') }}">Connexion</a>
                @endauth
                <a @class(['header-cta', 'account-link' => request()->routeIs('home'), 'button button-primary' => !request()->routeIs('home')]) href="{{ route('quote') }}">Demander un devis <span aria-hidden="true"><span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></span></a>
            </div>
            <button type="button" x-ref="menuButton" class="menu-toggle" @click="open = !open" :aria-expanded="open" aria-controls="mobile-menu" :aria-label="open ? 'Fermer le menu' : 'Ouvrir le menu'" aria-label="Ouvrir le menu"><span x-text="open ? 'Fermer' : 'Menu'">Menu</span> <span aria-hidden="true"><x-ui-icon name="menu" /></span></button>
        </div>
        <nav id="mobile-menu" class="mobile-nav" x-show="open" x-cloak @click="if ($event.target.closest('a')) open = false" aria-label="Navigation mobile">
            @foreach(['home' => 'Accueil', 'services' => 'Offres et tarifs', 'projects' => 'Réalisations', 'reviews' => 'Avis clients', 'about' => 'À propos', 'contact' => 'Contact', 'quote' => 'Demander un devis'] as $route => $label)<a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>@endforeach
            @auth
                <a href="{{ route('dashboard') }}">{{ auth()->user()->is_admin ? 'Administration' : 'Mon espace client' }}</a>
            @else
                <a href="{{ route('login') }}">Connexion</a><a href="{{ route('register') }}">Inscription</a>
            @endauth
        </nav>
    </header>
    @endif
    <main id="main">{{ $slot }}</main>
    <footer class="site-footer">
        <div class="container footer-top">
            <div><a class="brand footer-brand" href="{{ route('home') }}"><img src="{{ asset('images/codenyr-logo.webp') }}" alt="Codenyr" width="660" height="220" loading="lazy"></a><p>Le web, pensé pour votre activité.<br>Chalon-sur-Saône & partout en France.</p></div>
            <div><span class="eyebrow">Explorer</span><a href="{{ route('services') }}">Offres et tarifs</a><a href="{{ route('projects') }}">Réalisations</a><a href="{{ route('reviews') }}">Avis clients</a></div>
            <div><span class="eyebrow">Échangeons</span><a href="mailto:{{ config('codenyr.email') }}">{{ config('codenyr.email') }}</a><a href="{{ route('quote') }}">Demander un devis <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></a>@foreach(config('codenyr.social') as $name => $url)<a href="{{ $url }}" rel="noopener noreferrer" target="_blank">{{ $name }} <x-ui-icon name="arrow-up-right" /></a>@endforeach</div>
        </div>
        <div class="container footer-bottom"><span>© {{ date('Y') }} Codenyr. Tous droits réservés.</span><div><a href="{{ route('legal') }}">Mentions légales</a><a href="{{ route('privacy') }}">Confidentialité</a><a href="{{ route('terms') }}">CGU</a><button type="button" class="cookie-settings" id="cookie-settings">Gérer les cookies</button></div><span>Conçu avec soin. Développé sur mesure.</span></div>
    </footer>
    @unless(request()->routeIs('quote', 'contact', 'customer.*'))
        {{-- Phones only: a floating tab bar, with the quote request in the middle within thumb reach. --}}
        <nav class="mobile-cta" aria-label="Navigation rapide">
            @foreach(['home' => ['Accueil', 'home'], 'services' => ['Offres', 'tag'], 'quote' => ['Devis', 'document'], 'projects' => ['Réalisations', 'grid'], 'contact' => ['Contact', 'chat']] as $route => [$label, $icon])
                <a @class(['mobile-cta-quote' => $route === 'quote']) href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif><span class="mobile-cta-icon"><x-ui-icon :name="$icon" /></span>{{ $label }}</a>
            @endforeach
        </nav>
    @endunless
    <x-cookie-banner />
    @livewireScriptConfig
</body>
</html>
