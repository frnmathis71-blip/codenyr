{{-- Side panel on every admin page: the current conversation stays at hand while browsing. --}}
<div x-data="{
        open: (() => { try { return localStorage.getItem('assistant-panel') === 'open' } catch (e) { return false } })(),
        toggle(state) { this.open = state; try { localStorage.setItem('assistant-panel', state ? 'open' : 'closed') } catch (e) {} },
    }"
    x-effect="document.body.classList.toggle('assistant-panel-open', open)"
    x-on:keydown.escape.window="toggle(false)">
    <button type="button" class="assistant-launcher" x-show="! open" x-on:click="toggle(true)" aria-controls="assistant-panel">
        <span class="assistant-launcher-dot" @class(['is-busy' => $conversation?->running()]) aria-hidden="true"></span> Assistant
    </button>

    <aside id="assistant-panel" class="assistant-drawer" x-show="open" x-cloak aria-label="Assistant">
        <header class="assistant-drawer-head">
            <nav class="assistant-tabs" aria-label="Assistants">
                @foreach(\App\Services\AdminAssistant::AGENTS as $key => [$label])
                    <button type="button" wire:click="switchAgent('{{ $key }}')" @class(['active' => $agent === $key]) @if($agent === $key) aria-current="true" @endif>{{ $label }}</button>
                @endforeach
            </nav>
            <div class="assistant-drawer-actions">
                <button type="button" class="assistant-icon-button" wire:click="newConversation" title="Nouvelle conversation" aria-label="Nouvelle conversation">+</button>
                <a class="assistant-icon-button" href="{{ route($agent === 'projects' ? 'admin.assistant.projects' : 'admin.assistant') }}" title="Ouvrir en grand et voir l’historique" aria-label="Ouvrir l’assistant en pleine page">⤢</a>
                <button type="button" class="assistant-icon-button" x-on:click="toggle(false)" title="Fermer" aria-label="Fermer le panneau"><x-ui-icon name="close" /></button>
            </div>
        </header>
        @if($conversation?->title)<p class="assistant-drawer-title">{{ $conversation->title }}</p>@endif
        @unless($configured)
            <p class="assistant-provider"><small>Assistant non configuré : ajoute la clé API dans le fichier .env.</small></p>
        @endunless
        <div class="assistant-drawer-body">
            @include('livewire.admin.assistant-chat')
        </div>
    </aside>
</div>
