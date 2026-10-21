<?php

namespace App\Services;

/** One model response, whatever the provider. */
final readonly class AssistantTurn
{
    /**
     * @param  array<string, mixed>  $message  The response as stored in the history, in the provider's format.
     * @param  list<string>  $texts  Text shown to the admin.
     * @param  list<array{id: string, name: string, input: array<string, mixed>}>  $toolCalls
     * @param  'end'|'tool_use'|'refusal'|'max_tokens'  $stop
     */
    public function __construct(
        public array $message,
        public array $texts,
        public array $toolCalls,
        public string $stop,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadTokens = 0,
        public int $cacheWriteTokens = 0,
    ) {}
}
