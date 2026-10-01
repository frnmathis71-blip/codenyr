<x-site-layout title="Page introuvable" description="Cette page n’existe pas ou a été déplacée. Retrouvez les services et réalisations de Codenyr." :noindex="true">
<section class="container page-hero error-page"><p class="eyebrow">Erreur 404</p><h1>Cette page a pris<br><span class="muted-heading">un autre chemin.</span></h1><p>Le lien est peut-être ancien ou l’adresse comporte une erreur.</p><a class="button button-secondary" href="{{ route('home') }}">Retour à l’accueil <x-ui-icon name="arrow-right" /></a></section>
</x-site-layout>
