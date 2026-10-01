<x-site-layout title="Avis clients" description="Découvrez les avis des clients Codenyr sur la création de leur site internet et notre collaboration.">
    <section class="container page-hero">
        <p class="eyebrow">Leur expérience avec Codenyr</p>
        <h1>Vos projets.<br><span class="muted-heading">Vos mots.</span></h1>
        <p>Découvrez les retours de mes clients sur leur site et notre collaboration. Chaque avis est publié après validation.</p>
    </section>
    <section class="container section public-reviews-section" aria-label="Avis clients publiés">
        @if($testimonials->total())
            <p class="public-reviews-count">{{ $testimonials->total() }} {{ $testimonials->total() > 1 ? 'avis clients publiés' : 'avis client publié' }}</p>
            <div class="public-reviews-grid">@foreach($testimonials as $testimonial)<x-testimonial-card :testimonial="$testimonial" />@endforeach</div>
            @if($testimonials->hasPages())<div class="admin-pagination">{{ $testimonials->links() }}</div>@endif
        @else
            <div class="empty-state"><h2>Les premiers avis arrivent bientôt.</h2><p>Les retours de mes clients seront affichés ici après validation.</p><a class="button button-secondary" href="{{ route('projects') }}">Découvrir les réalisations <x-ui-icon name="arrow-right" /></a></div>
        @endif
        <div class="public-reviews-invitation"><div><h2>Nous avons travaillé ensemble ?</h2><p>Retrouvez votre projet livré dans votre espace client pour partager votre expérience.</p></div><a class="button button-secondary" href="{{ route('customer.dashboard') }}">Accéder à mon espace <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a></div>
    </section>
    <x-cta />
</x-site-layout>
