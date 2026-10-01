<div>
@if($submitted)
<section class="container section"><div class="success-panel" role="status" tabindex="-1" x-init="$el.focus()"><span class="success-icon" aria-hidden="true"><x-ui-icon name="check" /></span><p class="eyebrow">Message bien reçu</p><h1>Votre projet commence ici.</h1><p>Merci pour votre message. Votre demande a été enregistrée et un accusé de réception vous sera envoyé par e-mail. Je reviendrai vers vous pour en discuter.</p><a class="button button-secondary" href="{{ route('home') }}">Retour à l’accueil <x-ui-icon name="arrow-right" /></a></div></section>
@else
<section class="container page-hero"><p class="eyebrow">{{ $mode === 'contact' ? 'Contact' : 'Votre futur site commence ici' }}</p><h1>Parlons de <span class="muted-heading">votre projet.</span></h1><p>{{ $mode === 'contact' ? 'Une question, une idée, un premier échange. Je suis à votre écoute.' : 'Quelques mots sur votre activité et vos envies. Je m’occupe de la suite.' }}</p></section>
<section class="container section form-layout"><aside class="form-aside"><p class="eyebrow">Un échange simple, sans engagement</p><h2>Tout commence<br>par une conversation.</h2><p>Pas besoin d’un cahier des charges parfait. Expliquez-moi ce que vous souhaitez accomplir, nous préciserons le reste ensemble.</p><div class="aside-step"><span>01</span> Vous me présentez votre besoin.</div><div class="aside-step"><span>02</span> Nous échangeons sur votre projet.</div><div class="aside-step"><span>03</span> Vous recevez une proposition adaptée.</div><p style="margin-top:30px">Vous préférez écrire directement ?<br><a class="text-link" href="mailto:{{ config('codenyr.email') }}">{{ config('codenyr.email') }} <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></a></p><p>Basé à Chalon-sur-Saône.<br>À vos côtés en Bourgogne et à distance partout en France.</p>@foreach(config('codenyr.social') as $name => $url)<a class="text-link" href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $name }} <x-ui-icon name="arrow-up-right" /></a>@endforeach</aside>
<form wire:submit="submit" class="inquiry-form">
@if($errors->any())<div class="form-alert" role="alert">Certains champs nécessitent votre attention. Vérifiez les indications ci-dessous.@error('submit')<p>{{ $message }}</p>@enderror</div>@endif
<div class="honeypot" aria-hidden="true"><label for="fax">Laisser ce champ vide</label><input id="fax" wire:model="fax" type="text" tabindex="-1" autocomplete="off"></div>
<fieldset class="form-section"><legend><span>01 /</span> Faisons connaissance</legend><div class="form-grid">
@foreach(['firstname' => ['Prénom *','given-name'], 'lastname' => ['Nom *','family-name'], 'company' => ['Entreprise (facultatif)','organization'], 'email' => ['E-mail *','email'], 'phone' => ['Téléphone (facultatif)','tel']] as $field => [$label,$autocomplete])<div class="field"><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" wire:model="{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') }}" autocomplete="{{ $autocomplete }}" @if(in_array($field,['firstname','lastname','email'])) required @endif maxlength="{{ ['firstname'=>100, 'lastname'=>100, 'company'=>200, 'email'=>254, 'phone'=>30][$field] }}" @error($field) aria-invalid="true" aria-describedby="error-{{ $field }}" @enderror>@error($field)<span id="error-{{ $field }}" class="error">{{ $message }}</span>@enderror</div>@endforeach
</div></fieldset>
@if($mode === 'quote')
<fieldset class="form-section" x-data="{ selected: $wire.entangle('project_type') }">
    <legend><span>02 /</span> De quoi avez-vous besoin ?</legend>
    <div class="choice-grid">@foreach([...array_column(config('codenyr.offers'),'name'),'Je ne sais pas'] as $option)<label class="choice"><input type="radio" x-model="selected" value="{{ $option }}" name="project_type" required><span>{{ $option }}</span></label>@endforeach</div>
    @error('project_type')<span class="error">{{ $message }}</span>@enderror
    <div aria-live="polite">
        @foreach(config('codenyr.offers') as $offer)
            <section class="offer-summary" x-show="selected === @js($offer['name'])" x-cloak>
                <h3>{{ $offer['name'] }}</h3>
                <p>{{ $offer['description'] }}</p>
                <p><strong>{{ $offer['name'] === 'Sur mesure' ? 'Périmètre à définir ensemble :' : 'Ce site comprend :' }}</strong></p>
                <ul class="feature-list">@foreach($offer['features'] as $feature)<li>{{ $feature }}</li>@endforeach</ul>
            </section>
        @endforeach
        <p class="offer-summary" x-show="selected === 'Je ne sais pas'" x-cloak>Décrivez simplement votre activité et votre objectif. Nous choisirons ensemble le type de site adapté à votre projet.</p>
    </div>
</fieldset>
@endif
<fieldset class="form-section"><legend><span>{{ $mode === 'quote' ? '03' : '02' }} /</span> {{ $mode === 'quote' ? 'Quelques détails pour avancer' : 'Votre message' }}</legend><div class="form-grid">
@if($mode === 'contact')<div class="field full"><label for="subject">Sujet *</label><input id="subject" wire:model="subject" required maxlength="200">@error('subject')<span class="error">{{ $message }}</span>@enderror</div>@else
<div class="field full"><label for="desired_date">Délai souhaité (facultatif)</label><input id="desired_date" wire:model="desired_date" placeholder="Ex. : d’ici trois mois" maxlength="150">@error('desired_date')<span class="error">{{ $message }}</span>@enderror</div>
@endif
<div class="field full"><label for="description">{{ $mode === 'quote' ? 'Parlez-moi de votre projet *' : 'Message *' }}</label><textarea id="description" wire:model="description" rows="6" required minlength="20" maxlength="10000" placeholder="Votre activité, vos objectifs, vos envies…" @error('description') aria-invalid="true" aria-describedby="error-description" @enderror></textarea>@error('description')<span id="error-description" class="error">{{ $message }}</span>@enderror</div>
@if($mode === 'quote')<div class="field full"><label for="website">Votre site actuel (facultatif)</label><input id="website" type="url" wire:model="website" placeholder="https://" maxlength="255">@error('website')<span class="error">{{ $message }}</span>@enderror</div>@endif
</div></fieldset>
<label class="consent"><input type="checkbox" wire:model="consent" required><span>J’ai pris connaissance de la <a href="{{ route('privacy') }}" target="_blank" rel="noopener">politique de confidentialité</a> et comprends que mes données seront utilisées pour répondre à ma demande. *</span></label>@error('consent')<p class="error">{{ $message }}</p>@enderror
<button class="button button-primary" type="submit" wire:loading.attr="disabled"><span wire:loading.remove wire:target="submit">{{ $mode === 'quote' ? 'Envoyer ma demande' : 'Envoyer mon message' }} <x-ui-icon name="arrow-up-right" /></span><span wire:loading wire:target="submit">Envoi en cours…</span></button><p class="form-footer">* Champs obligatoires · Votre demande ne vous engage à rien.</p>
</form></section>
@endif
</div>
