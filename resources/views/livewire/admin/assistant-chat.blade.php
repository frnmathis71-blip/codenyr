{{-- Conversation and message form, shared by the full page and the side panel. --}}
{{-- Following a link from a reply keeps the conversation open in the side panel of the next page. --}}
<ol class="assistant-log" aria-live="polite" x-data
    x-init="$el.scrollTop = $el.scrollHeight; new MutationObserver(() => $el.scrollTop = $el.scrollHeight).observe($el, { childList: true, subtree: true })"
    x-on:click="if ($event.target.closest('a')) { try { localStorage.setItem('assistant-panel', 'open') } catch (e) {} }">
    @if(! $conversation)
        <li class="assistant-empty">
            @if($suggestions)
                <p>Pour commencer :</p>
                <div class="assistant-suggestions">
                    @foreach($suggestions as $index => $suggestion)
                        <button type="button" class="button button-secondary" wire:click="suggest({{ $index }})" @disabled(! $configured)>{{ $suggestion }}</button>
                    @endforeach
                </div>
                <p>Ou raconte une avancée, par exemple :</p>
                <blockquote>J’ai fini la maquette de la Boulangerie Martin, elle la valide demain. Elle voudrait décaler la mise en ligne au 15 décembre.</blockquote>
            @else
                <p>Par exemple :</p>
                <blockquote>Nouveau client : Boulangerie Martin, professionnel, contact Julie Martin, julie@boulangerie-martin.fr. Elle veut un site vitrine de 7 pages avec un formulaire de contact, livraison fin novembre, et la maintenance mensuelle.</blockquote>
            @endif
        </li>
    @endif
    @foreach($conversation?->log ?? [] as $index => $entry)
        <li wire:key="entry-{{ $index }}" @class(['assistant-entry', 'is-'.$entry['role']])>
            @if($entry['role'] === 'action')
                <x-ui-icon name="check" />
                @if($entry['url'])<a href="{{ $entry['url'] }}">{{ $entry['text'] }}</a>@else<span>{{ $entry['text'] }}</span>@endif
            @elseif($entry['role'] === 'assistant')
                <div class="assistant-text">{!! \App\Support\AssistantMarkup::render($entry['text']) !!}</div>
            @else
                <p>{{ $entry['text'] }}</p>
            @endif
        </li>
    @endforeach
    @if($conversation?->running())
        {{-- Resumes a reply left unfinished (page reloaded, or a step that failed); a stalled one ends with a message. --}}
        <li class="assistant-entry is-working" wire:init="process" wire:poll.15s="process">
            <span class="assistant-dots" aria-hidden="true"><i></i><i></i><i></i></span>
            {{ $conversation->waiting_on ? 'L’assistant administratif s’occupe de la paperasse…' : ($agent === 'projects' ? 'L’assistant consulte les dossiers…' : 'L’assistant prépare le dossier…') }}
        </li>
    @endif
</ol>

<form wire:submit="send" class="assistant-form">
    <label class="sr-only" for="assistant-message-{{ $panel ? 'panel' : 'page' }}">Ton message</label>
    <textarea id="assistant-message-{{ $panel ? 'panel' : 'page' }}" wire:model="message" rows="{{ $panel ? 2 : 3 }}" maxlength="10000" placeholder="{{ $agent === 'projects' ? 'Où en est un projet, avancée à noter, date à décaler…' : 'Nouveau client, modification d’un devis, information à ajouter…' }}" required @disabled(! $configured)
        x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit() }"></textarea>
    @error('message')<span class="error">{{ $message }}</span>@enderror
    <div class="assistant-form-foot">
        <small>Entrée pour envoyer, Maj + Entrée pour aller à la ligne.</small>
        <button class="button button-primary" wire:loading.attr="disabled" wire:target="send" @disabled(! $configured || $conversation?->running())>Envoyer</button>
    </div>
</form>
