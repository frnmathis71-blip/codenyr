<section class="admin-panel">
    <h2>Contrôler l’avis client</h2>
    <p><strong>{{ $review->client_name }}</strong> · {{ $review->company }} · Note : {{ $review->rating }}/5</p>
    <p class="fine-print">Projet CNY-{{ $review->lead_id }} · Version {{ $review->revision }} · {{ \App\Models\Testimonial::STATUSES[$review->moderation_status] }}</p>
    <blockquote class="moderation-content">{{ $review->content }}</blockquote>
    <p class="fine-print">L’avis est affiché dans les mots du client. Approuvez-le ou demandez-lui une modification.</p>
    <div class="field moderation-feedback"><label for="moderation-note">Retour au client (obligatoire en cas de refus)</label><textarea id="moderation-note" wire:model="moderation_note" rows="3" maxlength="2000"></textarea>@error('moderation_note')<span class="error" role="alert">{{ $message }}</span>@enderror</div>
    <div class="button-row">
        <button type="button" class="button button-primary" wire:click="moderate('approved')" wire:loading.attr="disabled">Approuver et publier</button>
        <button type="button" class="button button-secondary" wire:click="moderate('rejected')" wire:loading.attr="disabled">Refuser l’avis</button>
        <button type="button" class="button button-secondary" wire:click="$set('showForm', false)">Fermer</button>
    </div>
</section>
