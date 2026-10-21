<?php

namespace App\Livewire\Admin;

use App\Models\AssistantConversation;
use App\Services\AdminAssistant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Locked;

/**
 * The assistants' chat, as a full page or as the side panel of every admin page. Both
 * remember the current agent and conversation in the session, so the panel picks up
 * where the admin left off when following a link from a reply.
 */
class Assistant extends AdminComponent
{
    #[Locked]
    public string $agent = 'admin';

    #[Locked]
    public ?int $conversationId = null;

    #[Locked]
    public bool $panel = false;

    public string $message = '';

    public function mount(?string $agent = null, bool $panel = false): void
    {
        $this->panel = $panel;
        $current = session('assistant', []);
        $agent ??= $panel ? ($current['agent'] ?? 'admin') : 'admin';
        abort_unless(array_key_exists($agent, AdminAssistant::AGENTS), 404);
        $this->agent = $agent;
        $remembered = ($current['agent'] ?? null) === $agent ? ($current['conversation'] ?? null) : null;
        $this->conversationId = ($remembered ? $this->conversations()->whereKey($remembered)->value('id') : null)
            ?? $this->conversations()->latest('updated_at')->value('id');
        $this->remember();
    }

    /** Records the message at once; the reply is then built by successive process() calls. */
    public function send(): void
    {
        $data = $this->validate(['message' => 'required|string|max:10000']);
        abort_unless(AdminAssistant::configured(), 422);
        $conversation = $this->conversationId ? $this->conversation() : AssistantConversation::create(['user_id' => auth()->id(), 'agent' => $this->agent, 'provider' => AdminAssistant::provider()]);
        app(AdminAssistant::class)->expireIfStale($conversation);
        if ($conversation->running()) {
            $this->addError('message', 'L’assistant n’a pas fini de répondre au message précédent.');

            return;
        }
        $this->conversationId = $conversation->id;
        $this->remember();
        $this->message = '';
        app(AdminAssistant::class)->start($conversation, trim($data['message']));
        $this->js('$wire.process()');
    }

    /** One-click starters shown on an empty conversation. */
    public function suggest(int $index): void
    {
        $this->message = $this->suggestions()[$index] ?? abort(404);
        $this->send();
    }

    /** One step of the reply (one model call), so that no request outlasts the web server's timeout. */
    public function process(): void
    {
        if (! $this->conversationId) {
            return;
        }
        // One model call can exceed PHP's default 30 s; the web server still cuts at about a minute.
        set_time_limit(120);
        // Another tab may be advancing the same conversation: skip this turn and check again.
        $more = Cache::lock('assistant-conversation-'.$this->conversationId, 120)->get(fn (): bool => app(AdminAssistant::class)->step($this->conversation())) ?? true;
        if ($more) {
            $this->js('$wire.process()');
        }
    }

    public function open(int $id): void
    {
        $this->conversationId = $this->conversations()->findOrFail($id)->id;
        $this->remember();
    }

    public function newConversation(): void
    {
        $this->reset('conversationId', 'message');
        $this->remember();
    }

    /** Panel only: the full page switches agent with its tabs (links). */
    public function switchAgent(string $agent): void
    {
        abort_unless($this->panel && array_key_exists($agent, AdminAssistant::AGENTS), 404);
        $this->agent = $agent;
        $this->conversationId = $this->conversations()->latest('updated_at')->value('id');
        $this->remember();
    }

    private function remember(): void
    {
        session(['assistant' => ['agent' => $this->agent, 'conversation' => $this->conversationId]]);
    }

    /** @return Builder<AssistantConversation> */
    private function conversations(): Builder
    {
        return AssistantConversation::where('user_id', auth()->id())->where('agent', $this->agent);
    }

    private function conversation(): AssistantConversation
    {
        return $this->conversations()->findOrFail($this->conversationId);
    }

    /** @return list<string> */
    private function suggestions(): array
    {
        return $this->agent === 'projects'
            ? ['Fais le point sur mes projets : qu’est-ce que je dois faire en priorité ?', 'Quels projets sont en retard ou bloqués ?', 'Qu’est-ce qui reste à encaisser ?']
            : [];
    }

    public function render(): View
    {
        $data = [
            'conversation' => $this->conversationId ? $this->conversation() : null,
            'configured' => AdminAssistant::configured(),
            'provider' => AdminAssistant::provider(),
            'suggestions' => $this->suggestions(),
        ];
        if ($this->panel) {
            return view('livewire.admin.assistant-panel', $data);
        }

        return view('livewire.admin.assistant', [
            ...$data,
            'history' => $this->conversations()->latest('updated_at')->limit(20)->get(['id', 'title', 'updated_at']),
            'monthCost' => AssistantConversation::where('created_at', '>=', today()->startOfMonth())->get()->sum->costDollars(),
        ])->layout('components.admin-layout', ['title' => 'Assistant '.mb_strtolower(AdminAssistant::AGENTS[$this->agent][0])]);
    }
}
