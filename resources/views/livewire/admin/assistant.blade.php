<div>
    <p class="eyebrow">Codenyr / Organisation</p>
    <div class="dash-head">
        <div>
            <h1>Assistant</h1>
            <p>{{ $agent === 'projects'
                ? 'Ton chef de projet : demande-lui où en sont tes projets ou raconte une avancée. Il fait le point, met les dossiers à jour, et confie la paperasse (fiches, devis) à l’assistant administratif.'
                : 'Décris un client et son besoin : l’assistant crée la fiche, le projet, le devis en brouillon et les tâches de suivi. Tu relis et tu corriges ; rien n’est envoyé sans toi.' }}</p>
        </div>
        <button type="button" class="button button-secondary" wire:click="newConversation">Nouvelle conversation</button>
    </div>
    <nav class="assistant-tabs" aria-label="Assistants">
        @foreach(\App\Services\AdminAssistant::AGENTS as $key => [$label])
            <a href="{{ route($key === 'admin' ? 'admin.assistant' : 'admin.assistant.projects') }}" @class(['active' => $agent === $key]) @if($agent === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @unless($configured)
        <div class="form-alert" role="alert"><strong>Assistant non configuré</strong>
            @if($provider === 'gemini')
                <p>Ajoute ta clé API Gemini dans le fichier .env (<code>GEMINI_API_KEY=…</code>), puis recharge la page. La clé se crée gratuitement sur aistudio.google.com.</p>
            @else
                <p>Ajoute ta clé API Anthropic dans le fichier .env (<code>ANTHROPIC_API_KEY=…</code>), puis recharge la page. La clé se crée sur console.anthropic.com. Pour tester gratuitement avec Gemini, ajoute plutôt <code>CODENYR_ASSISTANT_PROVIDER=gemini</code> et <code>GEMINI_API_KEY=…</code>.</p>
            @endif
        </div>
    @elseif($provider === 'gemini')
        <p class="assistant-provider"><small>Fonctionne avec Gemini (niveau gratuit) : Google peut réutiliser ces échanges. Teste avec de faux clients, puis passe sur Claude pour les vrais dossiers.</small></p>
    @endunless

    <div class="assistant-layout">
        <section class="admin-panel assistant-chat" aria-label="Conversation avec l’assistant">
            @include('livewire.admin.assistant-chat')
        </section>

        <aside class="admin-panel assistant-history" aria-label="Conversations précédentes">
            <h2>Conversations</h2>
            @forelse($history as $item)
                <button type="button" wire:key="conv-{{ $item->id }}" wire:click="open({{ $item->id }})" @class(['assistant-history-item', 'active' => $item->id === $conversation?->id])>
                    <span>{{ $item->title ?: 'Sans titre' }}</span><small>{{ $item->updated_at->format('d/m à H:i') }}</small>
                </button>
            @empty
                <p class="dash-empty">Aucune conversation pour l’instant.</p>
            @endforelse
            <p class="assistant-cost"><small>Coût estimé ce mois-ci : {{ number_format($monthCost, 2, ',', ' ') }} $@if($conversation) · cette conversation : {{ number_format($conversation->costDollars(), 2, ',', ' ') }} $@endif</small></p>
        </aside>
    </div>
</div>
