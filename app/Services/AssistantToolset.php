<?php

namespace App\Services;

/**
 * One assistant agent: its instructions and the only actions it may perform.
 * Tools are described once, provider-neutral; each AssistantModel converts them.
 *
 * @phpstan-type ToolResult array{content: string, is_error: bool, actions: list<array{text: string, url: ?string}>, delegate?: int}
 *   "delegate": a conversation started for another agent; the result comes once it has finished.
 * @phpstan-type ToolSpec array{name: string, description: string, properties: array<string, array<string, mixed>>, required: list<string>}
 */
interface AssistantToolset
{
    /** Stable instructions (role, rules, reference data) sent as the system prompt. */
    public function prompt(): string;

    /** @return list<ToolSpec> */
    public function definitions(): array;

    /**
     * @param  array<string, mixed>  $input
     * @return ToolResult
     */
    public function run(string $name, array $input): array;
}
