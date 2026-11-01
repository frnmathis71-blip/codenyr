{{-- Phones only: a floating tab bar on every public page (it replaces the menu button), with the quote request in the middle within thumb reach. --}}
@php
    $tabs = [
        'home' => ['Accueil', 'home'],
        'services' => ['Offres', 'tag'],
        'quote' => ['Devis', 'document'],
        'projects' => ['Projets', 'grid'],
        'contact' => ['Contact', 'chat'],
    ];
    $tabs += auth()->check() ? ['dashboard' => ['Mon espace', 'user']] : ['login' => ['Connexion', 'user']];
@endphp
<nav class="mobile-cta" aria-label="Navigation rapide">
    @foreach($tabs as $route => [$label, $icon])
        <a @class(['mobile-cta-quote' => $route === 'quote']) href="{{ route($route) }}" @if(request()->routeIs($route === 'dashboard' ? ['dashboard', 'customer.*'] : $route)) aria-current="page" @endif><span class="mobile-cta-icon"><x-ui-icon :name="$icon" /></span>{{ $label }}</a>
    @endforeach
</nav>
