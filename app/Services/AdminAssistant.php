<?php

namespace App\Services;

use App\Models\AssistantConversation;
use Illuminate\Support\Str;

/**
 * Conversational admin assistants: the admin writes in plain French, the model acts
 * through the toolset of the conversation's agent (paperwork or project follow-up).
 *
 * Work advances one model call per step(), so each web request stays short (servers
 * cut requests after about a minute); the page calls step() again until the reply is
 * complete. The history is append-only so the model's reasoning traces replay unchanged.
 *
 * @phpstan-import-type ToolResult from AssistantToolset
 */
class AdminAssistant
{
    /** agent key => [label, toolset]. */
    public const AGENTS = [
        'admin' => ['Administratif', AssistantTools::class],
        'projects' => ['Projets', ProjectTools::class],
    ];

    /** provider key => model; chosen with CODENYR_ASSISTANT_PROVIDER. */
    public const PROVIDERS = [
        'anthropic' => AnthropicModel::class,
        'gemini' => GeminiModel::class,
    ];

    /** Model calls allowed for one admin message (each tool round trip is one call). */
    private const MAX_STEPS = 15;

    /** A reply that has not progressed for this long was interrupted (crashed request, closed page). */
    private const STALE_MINUTES = 5;

    /** Provider used for new conversations. */
    public static function provider(): string
    {
        $provider = (string) config('services.assistant.provider');

        return array_key_exists($provider, self::PROVIDERS) ? $provider : 'anthropic';
    }

    public static function model(?string $provider = null): AssistantModel
    {
        return app(self::PROVIDERS[$provider ?? self::provider()] ?? AnthropicModel::class);
    }

    public static function configured(): bool
    {
        return self::model()->configured();
    }

    /** Runs a whole reply in one go (tests, console); the page uses start() then step(). */
    public function reply(AssistantConversation $conversation, string $text, ?string $shown = null): void
    {
        $this->start($conversation, $text, $shown);
        while ($this->step($conversation)) {
            // Each step is one model call or one finished delegation.
        }
    }

    /** @param  string|null  $shown  What the conversation displays, when the model receives more (e.g. a delegated request with its project file). */
    public function start(AssistantConversation $conversation, string $text, ?string $shown = null): void
    {
        $model = self::model($conversation->provider);
        $log = $conversation->log;
        $log[] = ['role' => 'user', 'text' => $shown ?? $text];
        $conversation->title ??= Str::limit($shown ?? $text, 80);
        if (! $model->configured()) {
            $log[] = ['role' => 'error', 'text' => 'Cette conversation a été commencée avec '.$model->label().', qui n’est plus configuré. Commencez une nouvelle conversation.'];
            $conversation->fill(['log' => $log, 'status' => 'idle'])->save();

            return;
        }
        $messages = $conversation->messages;
        $messages[] = $model->userMessage('[Date du jour : '.today()->format('d/m/Y')."]\n".$text);
        $conversation->fill(['messages' => $messages, 'log' => $log, 'status' => 'running', 'steps' => 0, 'pending' => null, 'waiting_on' => null])->save();
    }

    /** Advances the reply by one unit of work. Returns whether more work remains. */
    public function step(AssistantConversation $conversation): bool
    {
        if (! $conversation->running()) {
            return false;
        }
        if ($this->expireIfStale($conversation)) {
            return false;
        }
        $tools = app(self::AGENTS[$conversation->agent][1] ?? AssistantTools::class);
        $model = self::model($conversation->provider);

        // Waiting for a delegated conversation: advance it, or collect its report once finished.
        if ($conversation->waiting_on) {
            $child = AssistantConversation::find($conversation->waiting_on);
            if ($child?->running()) {
                $this->step($child);
                // The delegated work is this reply's progress.
                $conversation->touch();

                return true;
            }
            $result = $child ? app(ProjectTools::class)->delegationReport($child) : ['content' => 'La conversation déléguée a disparu.', 'is_error' => true, 'actions' => []];
            $conversation->waiting_on = null;
            $this->record($conversation, $result);
            $this->runTools($conversation, $tools, $model);

            return true;
        }
        if ($conversation->pending) {
            $this->runTools($conversation, $tools, $model);

            return true;
        }

        if ($conversation->steps >= self::MAX_STEPS) {
            return $this->finish($conversation, 'L’assistant s’est arrêté après de nombreuses étapes. Écrivez « continue » pour qu’il reprenne.');
        }
        try {
            $turn = $model->call($tools->prompt(), array_values($conversation->messages), $tools->definitions());
        } catch (AssistantModelException $e) {
            // The history ends on a user turn; the next admin message follows it, nothing is rewritten.
            return $this->finish($conversation, $e->getMessage());
        }

        $conversation->steps++;
        $conversation->input_tokens += $turn->inputTokens;
        $conversation->output_tokens += $turn->outputTokens;
        $conversation->cache_read_tokens += $turn->cacheReadTokens;
        $conversation->cache_write_tokens += $turn->cacheWriteTokens;
        $messages = $conversation->messages;
        $log = $conversation->log;
        if ($turn->message !== []) {
            $messages[] = $turn->message;
        }
        foreach ($turn->texts as $reply) {
            $log[] = ['role' => 'assistant', 'text' => $reply];
        }
        $conversation->fill(['messages' => $messages, 'log' => $log]);

        if ($turn->stop !== 'tool_use') {
            return $this->finish($conversation, match ($turn->stop) {
                'refusal' => $model->label().' a refusé de traiter cette demande. Reformulez-la ou faites cette étape à la main.',
                'max_tokens' => 'La réponse a été coupée car trop longue. Demandez à l’assistant de continuer.',
                default => null,
            });
        }
        $conversation->pending = ['calls' => $turn->toolCalls, 'results' => [], 'index' => 0];
        $this->runTools($conversation, $tools, $model);

        return true;
    }

    /** Executes the remaining tool calls of the turn, stopping at a delegation to wait for it. */
    private function runTools(AssistantConversation $conversation, AssistantToolset $tools, AssistantModel $model): void
    {
        while ($conversation->pending && $conversation->pending['index'] < count($conversation->pending['calls'])) {
            $call = $conversation->pending['calls'][$conversation->pending['index']];
            $result = $tools->run($call['name'], $call['input']);
            if (isset($result['delegate'])) {
                $conversation->waiting_on = $result['delegate'];
                $conversation->save();

                return;
            }
            $this->record($conversation, $result);
        }
        if ($conversation->pending) {
            // All results of one turn go back together, in a single message.
            $messages = $conversation->messages;
            $messages[] = $model->toolResults($conversation->pending['results']);
            $conversation->fill(['messages' => $messages, 'pending' => null]);
        }
        $conversation->save();
    }

    /**
     * Stores the result of the current tool call and moves to the next one.
     *
     * @param  ToolResult  $result
     */
    private function record(AssistantConversation $conversation, array $result): void
    {
        $pending = $conversation->pending ?? ['calls' => [], 'results' => [], 'index' => 0];
        $call = $pending['calls'][$pending['index']];
        $pending['results'][] = ['id' => $call['id'], 'name' => $call['name'], 'content' => $result['content'], 'is_error' => $result['is_error']];
        $pending['index']++;
        $log = $conversation->log;
        foreach ($result['actions'] as $action) {
            $log[] = ['role' => 'action', ...$action];
        }
        $conversation->fill(['pending' => $pending, 'log' => $log]);
    }

    /** Ends a reply that stopped progressing, so the conversation can take a new message. */
    public function expireIfStale(AssistantConversation $conversation): bool
    {
        if (! $conversation->running() || ! $conversation->updated_at?->lt(now()->subMinutes(self::STALE_MINUTES))) {
            return false;
        }
        $this->finish($conversation, 'La réponse a été interrompue. Renvoyez votre message pour que l’assistant reprenne.');

        return true;
    }

    private function finish(AssistantConversation $conversation, ?string $error): bool
    {
        $log = $conversation->log;
        if ($error) {
            $log[] = ['role' => 'error', 'text' => $error];
        }
        $conversation->fill(['log' => $log, 'status' => 'idle'])->save();

        return false;
    }
}
