@php($legal = config('codenyr.legal'))
<x-site-layout title="Mentions légales" description="Informations relatives à l’éditeur, à l’hébergement et à la propriété intellectuelle du site Codenyr.">
<section class="container page-hero"><p class="eyebrow">Informations légales</p><h1>Mentions légales.</h1><p>Dernière mise à jour : 14 octobre 2026.</p></section>
<section class="container section prose legal-prose">
    <h2>Éditeur du site</h2>
    <p>Codenyr est le nom commercial de <strong>{{ $legal['owner'] }}</strong>, {{ $legal['status'] }}.<br>
    Adresse : {{ $legal['address'] }}<br>
    Téléphone : <a href="tel:{{ preg_replace('/\D/', '', $legal['phone']) }}">{{ $legal['phone'] }}</a><br>
    E-mail : <a href="mailto:{{ config('codenyr.email') }}">{{ config('codenyr.email') }}</a><br>
    SIRET : {{ $legal['siret'] ?: 'immatriculation en cours' }}<br>
    TVA : {{ $legal['vat'] }}</p>
    <p>Responsable de la publication : {{ $legal['owner'] }}.</p>

    <h2>Hébergement</h2>
    <p>Le site est hébergé par <strong>{{ $legal['host']['name'] }}</strong>, {{ $legal['host']['registration'] }}.<br>
    Adresse : {{ $legal['host']['address'] }}<br>
    Téléphone : {{ $legal['host']['phone'] }}<br>
    Site : <a href="{{ $legal['host']['url'] }}" rel="noopener noreferrer" target="_blank">{{ str_replace('https://', '', $legal['host']['url']) }}</a></p>

    <h2>Propriété intellectuelle</h2>
    <p>Les textes, logos, éléments graphiques et développements du site sont la propriété de leur auteur ou de leurs titulaires respectifs. Toute reproduction ou réutilisation nécessite une autorisation préalable, sauf exception prévue par la loi.</p>
    <p>Les marques, projets et clients présentés dans les démonstrations sont fictifs. Les photographies d’ambiance utilisées dans ces démonstrations proviennent de banques d’images sous licence permettant cet usage ; leurs droits restent ceux de leurs auteurs.</p>

    <h2>Données personnelles</h2>
    <p>Consultez la <a href="{{ route('privacy') }}">politique de confidentialité</a> pour connaître les traitements de données, leurs durées de conservation et vos droits.</p>

    <h2>Médiation de la consommation</h2>
    <p>Conformément aux articles L611-1 et suivants du Code de la consommation, le client consommateur peut recourir gratuitement à un médiateur de la consommation en vue de la résolution amiable d’un litige, après avoir tenté de le résoudre directement par écrit auprès de Codenyr.</p>
    <p>Médiateur : {{ $legal['mediator'] ?: 'désignation en cours.' }}</p>
    <p>Les conditions applicables à chaque prestation (prix, délais, droit de rétractation lorsqu’il s’applique) sont précisées dans le devis et les conditions générales de vente remises avant toute commande.</p>
</section>
</x-site-layout>
