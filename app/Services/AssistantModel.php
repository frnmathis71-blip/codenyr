<?php

namespace App\Services;

/**
 * One AI provider behind the admin assistants. Messages are kept in the provider's own
 * format and replayed unchanged (both providers require their reasoning traces back),
 * so a conversation always stays on the provider that started it.
 *
 * @phpstan-import-type ToolSpec from AssistantToolset
 */
interface AssistantModel
{
    /** Shown in error messages and on the page. */
    public function label(): string;

    public function configured(): bool;

    /** @return array<string, mixed> */
    public function userMessage(string $text): array;

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<ToolSpec>  $tools
     *
     * @throws AssistantModelException with a message the admin can act on
     */
    public function call(string $system, array $messages, array $tools): AssistantTurn;

    /**
     * All results of one turn, as a single message.
     *
     * @param  list<array{id: string, name: string, content: string, is_error: bool}>  $results
     * @return array<string, mixed>
     */
    public function toolResults(array $results): array;

    /**
     * Dollars for one million tokens.
     *
     * @return array{input: float, output: float, cache_read: float, cache_write: float}
     */
    public function prices(): array;
}
