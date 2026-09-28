<x-mail::message>
# {{ $confirmation ? 'Merci pour votre confiance.' : 'Une nouvelle demande pour Codenyr.' }}

@if($confirmation)
Bonjour {{ $lead->firstname }},

Votre demande a bien été enregistrée sous la référence **CNY-{{ $lead->id }}**. Je prendrai connaissance de votre projet et reviendrai vers vous pour préciser vos besoins et les prochaines étapes.

Cet accusé de réception ne constitue ni un devis ni un engagement contractuel.
@else
**{{ $lead->firstname }} {{ $lead->lastname }}** — {{ $lead->company ?: 'Particulier / entreprise non renseignée' }}

E-mail : {{ $lead->email }}  
Téléphone : {{ $lead->phone ?: 'Non renseigné' }}
@endif

## Votre demande

Type : {{ $lead->project_type }}  
Budget : {{ $lead->budget ?: 'À définir' }}  
Délai souhaité : {{ $lead->desired_date ?: 'À définir' }}  
Site existant : {{ $lead->website ?: 'Non renseigné' }}  
Fonctionnalités : {{ implode(', ', $lead->features ?? []) ?: 'À définir' }}

{{ $lead->description }}

@unless($confirmation)
<x-mail::button :url="route('admin.leads')">Consulter les prospects</x-mail::button>
@endunless

À bientôt,  
Codenyr  
{{ config('codenyr.email') }}
</x-mail::message>
