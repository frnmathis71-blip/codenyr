<x-site-layout title="Avis clients" description="Découvrez les avis des clients Codenyr sur la création de leur site internet et notre collaboration.">
    <section class="container page-hero">
        <p class="eyebrow">Leur expérience avec Codenyr</p>
        <h1>Vos projets.<br><span class="muted-heading">Vos mots.</span></h1>
        <p>Découvrez les retours de mes clients sur leur site et notre collaboration. Chaque avis est publié après validation. <a href="#fonctionnement-avis">Comment les avis sont recueillis</a></p>
    </section>
    <section class="container section public-reviews-section" aria-label="Avis clients publiés">
        @if($testimonials->total())
            <p class="public-reviews-count">{{ $testimonials->total() }} {{ $testimonials->total() > 1 ? 'avis clients publiés' : 'avis client publié' }}</p>
            <div class="public-reviews-grid">@foreach($testimonials as $testimonial)<x-testimonial-card :testimonial="$testimonial" />@endforeach</div>
            @if($testimonials->hasPages())<div class="admin-pagination">{{ $testimonials->links() }}</div>@endif
        @else
            <div class="empty-state"><h2>Les premiers avis arrivent bientôt.</h2><p>Les retours de mes clients seront affichés ici après validation.</p><a class="button button-secondary" href="{{ route('projects') }}">Découvrir les réalisations <x-ui-icon name="arrow-right" /></a></div>
        @endif
        <section id="fonctionnement-avis" class="public-reviews-notice" aria-labelledby="reviews-notice-title">
            <h2 id="reviews-notice-title">Comment les avis sont recueillis</h2>
            <ul>
                <li><strong>Qui peut déposer un avis :</strong> un client dont le devis a été accepté et le site livré, depuis son espace client. Codenyr peut aussi saisir un avis transmis directement par un client, avec son accord ; il est alors indiqué « Recueilli par Codenyr ».</li>
                <li><strong>Contrôle :</strong> chaque avis est relu avant publication pour vérifier qu’il provient d’un client et respecte les règles de publication. Codenyr ne peut pas modifier le contenu d’un avis déposé depuis l’espace client ; toute modification par son auteur entraîne une nouvelle validation.</li>
                <li><strong>Ordre d’affichage :</strong> du plus récent au plus ancien. Chaque avis indique sa date.</li>
                <li><strong>Retrait :</strong> l’auteur peut retirer son accord à tout moment depuis son espace client ; l’avis est alors masqué.</li>
            </ul>
        </section>
        <div class="public-reviews-invitation"><div><h2>Nous avons travaillé ensemble ?</h2><p>Retrouvez votre projet livré dans votre espace client pour partager votre expérience.</p></div><a class="button button-secondary" href="{{ route('customer.dashboard') }}">Accéder à mon espace <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a></div>
    </section>
    <x-cta />
</x-site-layout>
