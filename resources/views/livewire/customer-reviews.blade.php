<div>
    <section class="container page-hero customer-hero">
        <p class="eyebrow">Mon espace client</p>
        <h1>Vos projets et <span class="muted-heading">documents.</span></h1>
        <p>Bonjour {{ auth()->user()->name }}. Retrouvez les documents de vos projets et partagez votre expérience avec Codenyr.</p>
        <div class="button-row customer-actions">
            <a class="button button-secondary" href="{{ route('profile.edit') }}">Mon compte</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="button button-secondary" type="submit">Déconnexion</button></form>
        </div>
    </section>
    <section class="container section customer-content">
        @if(session('success'))<div class="flash" role="status">{{ session('success') }}</div>@endif

        <h2>Mes projets et documents</h2>
        <div class="customer-projects">
            @forelse($projects as $project)
                <article class="customer-project" wire:key="commercial-project-{{ $project->id }}">
                    <div><p class="eyebrow">Projet #{{ $project->id }}</p><h3>{{ $project->name }}</h3><span class="status-pill">{{ $project->archived_at ? 'Archivé' : \App\Models\ClientProject::STATUSES[$project->status] }}</span></div>
                    <div>
                        @forelse($documents->where('client_project_id', $project->id) as $document)
                            <div class="customer-review" wire:key="customer-document-{{ $document->id }}">
                                <p><strong>{{ $document->name }}</strong><br>{{ \App\Models\Document::TYPES[$document->type] }} · {{ $document->document_date->format('d/m/Y') }}</p>
                                <a class="button button-secondary" href="{{ route('customer.documents.download', $document->id) }}">Télécharger</a>
                            </div>
                        @empty
                            <p>Aucun document partagé pour ce projet pour le moment.</p>
                        @endforelse
                    </div>
                </article>
            @empty
                <div class="empty-state"><p>Aucun dossier commercial rattaché à votre compte pour le moment. Communiquez l’adresse e-mail de votre compte à Codenyr pour retrouver votre projet.</p></div>
            @endforelse
        </div>
        <section class="customer-project">
            <h2>Mes données personnelles</h2>
            <p>Vous pouvez <a href="{{ route('profile.edit') }}">modifier ou supprimer votre compte</a>, demander une copie de vos données ou exercer vos autres droits. Les pièces devant être conservées pour une obligation légale ne sont pas supprimées avec le compte.</p>
            <form wire:submit="requestPrivacy">
                <div class="field"><label for="privacy-type">Votre demande</label><select id="privacy-type" wire:model="privacyType">
                    @foreach(\App\Models\PrivacyRequest::TYPES as $value=>$label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>@error('privacyType')<p class="error">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="privacy-message">Précisions (facultatif)</label><textarea id="privacy-message" wire:model="privacyMessage" rows="3" maxlength="5000"></textarea>@error('privacyMessage')<p class="error">{{ $message }}</p>@enderror</div>
                <button class="button button-secondary" wire:loading.attr="disabled">Enregistrer ma demande</button>
            </form>
            <p>Vous pouvez également écrire à <a href="mailto:{{ config('codenyr.email') }}">{{ config('codenyr.email') }}</a>, même sans compte.</p>
            @foreach($privacyRequests as $request)
                <div class="customer-review" wire:key="my-privacy-{{ $request->id }}"><strong>{{ \App\Models\PrivacyRequest::TYPES[$request->type] }} — {{ $request->resolved_at ? 'Réponse disponible' : 'En cours' }}</strong><p>Demande du {{ $request->created_at->format('d/m/Y') }} · Échéance initiale : {{ $request->due_at->format('d/m/Y') }}</p>@if($request->response)<p class="preserve-lines">{{ $request->response }}</p>@endif</div>
            @endforeach
            @foreach($myReviews as $review)
                @unless($review->withdrawn_at)
                    <p>Avis du {{ $review->created_at->format('d/m/Y') }} : {{ Str::limit($review->content, 100) }}</p><button class="button button-secondary" wire:click="withdrawReview({{ $review->id }})">Retirer mon accord de publication</button>
                @endunless
            @endforeach
        </section>
        <h2>Mes avis</h2>
        <div class="notice"><strong>Un avis authentique, publié après vérification.</strong><p>Vous pouvez déposer un avis lorsque Codenyr a accepté votre devis, confirmé la livraison de votre site et associé le projet à votre compte. Toute modification d’un avis déjà publié le remet en attente de validation.</p></div>
        @if($selected)
            <form wire:submit="submit" class="inquiry-form review-form">
                <h2>Partagez votre expérience</h2>
                <p>Votre nom et votre entreprise accompagneront l’avis s’il est publié.</p>
                <div class="form-grid">
                    <div class="field full"><label for="review-rating">Votre note *</label><select id="review-rating" wire:model="rating">@foreach(range(5,1) as $score)<option value="{{ $score }}">{{ $score }} / 5</option>@endforeach</select>@error('rating')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field full"><label for="review-content">Votre avis *</label><textarea id="review-content" wire:model="content" rows="6" required minlength="20" maxlength="5000" placeholder="Comment s’est déroulée notre collaboration ? Qu’appréciez-vous dans votre nouveau site ?"></textarea>@error('content')<span class="error" role="alert">{{ $message }}</span>@enderror</div>
                </div>
                <label class="consent"><input type="checkbox" wire:model="consent" required><span>J’autorise Codenyr à publier mon avis, ma note, mon nom et mon entreprise sur son site après validation. Je confirme que cet avis reflète mon expérience réelle.</span></label>
                @error('consent')<p class="error">{{ $message }}</p>@enderror
                <div class="button-row"><button type="submit" class="button button-primary" wire:loading.attr="disabled">Soumettre mon avis <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-right" /></span></button><button type="button" class="button button-secondary" wire:click="cancel">Annuler</button></div>
            </form>
        @endif
        <div class="customer-projects">
            @forelse($leads as $lead)
                <article class="customer-project" wire:key="customer-project-{{ $lead->id }}">
                    <div><p class="eyebrow">Projet CNY-{{ $lead->id }}</p><h2>{{ $lead->project_type }}</h2><p>{{ $lead->company }}</p></div>
                    <div class="tags"><span>Devis : {{ \App\Models\Lead::STATUSES[$lead->status] }}</span><span>{{ $lead->delivered_at ? 'Site livré' : 'Livraison non confirmée' }}</span></div>
                    @if($lead->testimonial)
                        <div class="customer-review"><span class="status-pill">Avis : {{ \App\Models\Testimonial::STATUSES[$lead->testimonial->moderation_status] }}</span><p class="review-score">{{ $lead->testimonial->rating }} / 5</p><blockquote>{{ $lead->testimonial->content }}</blockquote>@if($lead->testimonial->moderation_note)<div class="notice"><strong>Retour de Codenyr</strong><p>{{ $lead->testimonial->moderation_note }}</p></div>@endif</div>
                    @endif
                    @if($lead->status === 'accepted' && $lead->delivered_at)
                        <button class="button button-secondary" wire:click="edit({{ $lead->id }})">{{ $lead->testimonial ? 'Modifier mon avis' : 'Donner mon avis' }} <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-up-right" /></span></button>
                    @else
                        <p class="fine-print">Le dépôt d’avis sera disponible après acceptation du devis et confirmation de la livraison.</p>
                    @endif
                </article>
            @empty
                <div class="empty-state"><h2>Aucun projet associé pour le moment.</h2><p>Vous avez déjà travaillé avec Codenyr ? Contactez-moi avec l’adresse e-mail de ce compte pour que je puisse y rattacher votre projet.</p><a class="button button-secondary" href="{{ route('contact') }}">Contacter Codenyr <span class="mobile-decoration" aria-hidden="true"><x-ui-icon name="arrow-right" /></span></a></div>
            @endforelse
        </div>
    </section>
</div>
