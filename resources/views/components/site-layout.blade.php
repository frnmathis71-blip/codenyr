@props(['title' => 'Création de sites internet à Chalon-sur-Saône', 'description' => 'Codenyr développe des sites internet sur mesure pour les artisans, commerçants et entreprises à Chalon-sur-Saône. Design soigné, tarifs transparents et accompagnement local.', 'image' => null])
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Codenyr</title>
    <meta name="description" content="{{ $description }}">
    @if(request()->routeIs('customer.*'))<meta name="robots" content="noindex,nofollow">@endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:title" content="{{ $title }} — Codenyr">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $image ?? asset('images/codenyr.png') }}">
    <meta name="theme-color" content="#0D1117">
    <link rel="icon" type="image/png" href="{{ asset('images/codenyr.png') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'ProfessionalService', 'name' => 'Codenyr', 'url' => url('/'), 'logo' => asset('images/codenyr.png'), 'email' => config('codenyr.email'), 'areaServed' => ['@type' => 'City', 'name' => 'Chalon-sur-Saône']], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
</head>
<body class="site-body">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <header class="site-header" x-data="{ open: false }" @keydown.escape.window="if (open) { open = false; $refs.menuButton.focus() }">
        <div class="container header-inner">
            <a href="{{ route('home') }}" aria-label="Codenyr, accueil" class="brand"><img src="{{ asset('images/codenyr.png') }}" width="2172" height="724" alt="Codenyr"></a>
            <nav class="desktop-nav" aria-label="Navigation principale">
                @foreach(['home' => 'Accueil', 'services' => 'Services', 'projects' => 'Réalisations', 'reviews' => 'Avis clients', 'pricing' => 'Tarifs', 'about' => 'À propos', 'contact' => 'Contact'] as $route => $label)
                    <a href="{{ route($route) }}" @class(['active' => request()->routeIs($route)]) @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="header-account-actions">
                @auth
                    <a class="account-link" href="{{ route('dashboard') }}">{{ auth()->user()->is_admin ? 'Administration' : 'Mon espace' }}</a>
                @else
                    <a class="account-link" href="{{ route('login') }}">Connexion</a>
                    @unless(request()->routeIs('home'))<a class="account-link account-register" href="{{ route('register') }}">Inscription</a>@endunless
                @endauth
                <a class="button button-primary header-cta" href="{{ route('quote') }}">Demander un devis <span aria-hidden="true">↗</span></a>
            </div>
            <button type="button" x-ref="menuButton" class="menu-toggle" @click="open = !open" :aria-expanded="open" aria-controls="mobile-menu" :aria-label="open ? 'Fermer le menu' : 'Ouvrir le menu'" aria-label="Ouvrir le menu"><span x-text="open ? 'Fermer' : 'Menu'">Menu</span> <span aria-hidden="true">☰</span></button>
        </div>
        <nav id="mobile-menu" class="mobile-nav" x-show="open" x-cloak @click="if ($event.target.closest('a')) open = false" aria-label="Navigation mobile">
            @foreach(['home' => 'Accueil', 'services' => 'Services', 'projects' => 'Réalisations', 'reviews' => 'Avis clients', 'pricing' => 'Tarifs', 'about' => 'À propos', 'contact' => 'Contact', 'quote' => 'Demander un devis'] as $route => $label)<a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>@endforeach
            @auth
                <a href="{{ route('dashboard') }}">{{ auth()->user()->is_admin ? 'Administration' : 'Mon espace client' }}</a>
            @else
                <a href="{{ route('login') }}">Connexion</a><a href="{{ route('register') }}">Inscription</a>
            @endauth
        </nav>
    </header>
    <main id="main">{{ $slot }}</main>
    <footer class="site-footer">
        <div class="container footer-top">
            <div><a class="brand footer-brand" href="{{ route('home') }}"><img src="{{ asset('images/codenyr.png') }}" alt="Codenyr" width="2172" height="724" loading="lazy"></a><p>Le web, pensé pour votre activité.<br>Chalon-sur-Saône & partout en France.</p></div>
            <div><span class="eyebrow">Explorer</span><a href="{{ route('services') }}">Services</a><a href="{{ route('projects') }}">Réalisations</a><a href="{{ route('pricing') }}">Tarifs</a><a href="{{ route('reviews') }}">Avis clients</a></div>
            <div><span class="eyebrow">Échangeons</span><a href="mailto:{{ config('codenyr.email') }}">{{ config('codenyr.email') }}</a><a href="{{ route('quote') }}">Parler de votre projet ↗</a>@foreach(config('codenyr.social') as $name => $url)<a href="{{ $url }}" rel="noopener noreferrer" target="_blank">{{ $name }} ↗</a>@endforeach</div>
        </div>
        <div class="container footer-bottom"><span>© {{ date('Y') }} Codenyr. Tous droits réservés.</span><div><a href="{{ route('legal') }}">Mentions légales</a><a href="{{ route('privacy') }}">Confidentialité</a></div><span>Conçu avec soin. Développé sur mesure.</span></div>
    </footer>
    @livewireScriptConfig
</body>
</html>
