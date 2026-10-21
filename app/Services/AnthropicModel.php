<?php

namespace App\Services;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Beta\Messages\BetaTool;
use Anthropic\Beta\Messages\BetaTool\InputSchema;
use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

/** Claude, through the official Anthropic SDK. */
class AnthropicModel implements AssistantModel
{
    public function label(): string
    {
        return 'Claude';
    }

    public function configured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public function userMessage(string $text): array
    {
        return ['role' => 'user', 'content' => $text];
    }

    public function call(string $system, array $messages, array $tools): AssistantTurn
    {
        try {
            $response = app(Client::class)->beta->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 16000,
                system: [['type' => 'text', 'text' => $system]],
                messages: $this->replayable($messages),
                tools: array_map(fn (array $tool): BetaTool => BetaTool::with(name: $tool['name'], description: $tool['description'], inputSchema: InputSchema::with(properties: $tool['properties'], required: $tool['required'])), $tools),
                outputConfig: ['effort' => config('services.anthropic.effort')],
                cacheControl: ['type' => 'ephemeral'],
                // On a policy decline, the API retries on its default fallback model in the same call.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException) {
            throw new AssistantModelException('La clé API Anthropic est refusée. Vérifiez ANTHROPIC_API_KEY dans le fichier .env.');
        } catch (RateLimitException) {
            throw new AssistantModelException('Trop de demandes envoyées à Claude pour le moment. Réessayez dans une minute.');
        } catch (APIStatusException $e) {
            report($e);

            throw new AssistantModelException('Claude n’a pas pu répondre (erreur '.$e->status.'). Réessayez dans un instant.');
        } catch (APIConnectionException $e) {
            report($e);

            throw new AssistantModelException('Impossible de joindre Claude. Vérifiez la connexion internet du serveur.');
        }

        $texts = $calls = [];
        foreach ($response->content as $block) {
            if ($block instanceof BetaTextBlock && trim($block->text) !== '') {
                $texts[] = $block->text;
            } elseif ($block instanceof BetaToolUseBlock) {
                $calls[] = ['id' => $block->id, 'name' => $block->name, 'input' => $block->input];
            }
        }
        $usage = $response->usage;

        return new AssistantTurn(
            message: ['role' => 'assistant', 'content' => json_decode((string) json_encode($response->content), true)],
            texts: $texts,
            toolCalls: $calls,
            stop: match ($response->stopReason) {
                'tool_use' => 'tool_use', 'refusal' => 'refusal', 'max_tokens' => 'max_tokens', default => 'end'
            },
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheReadTokens: $usage->cacheReadInputTokens ?? 0,
            cacheWriteTokens: $usage->cacheCreationInputTokens ?? 0,
        );
    }

    public function toolResults(array $results): array
    {
        return ['role' => 'user', 'content' => array_map(fn (array $result): array => ['type' => 'tool_result', 'tool_use_id' => $result['id'], 'content' => $result['content'], 'is_error' => $result['is_error']], $results)];
    }

    public function prices(): array
    {
        return config('services.anthropic.prices');
    }

    /**
     * Stored blocks went through json_decode(…, true): an empty tool input became [] and
     * must be sent back as the {} object the API produced.
     *
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    private function replayable(array $messages): array
    {
        foreach ($messages as &$message) {
            if (is_array($message['content'])) {
                foreach ($message['content'] as &$block) {
                    if (in_array($block['type'] ?? null, ['tool_use', 'server_tool_use'], true) && ($block['input'] ?? []) === []) {
                        $block['input'] = new \stdClass;
                    }
                }
                unset($block);
            }
        }

        return $messages;
    }
}
