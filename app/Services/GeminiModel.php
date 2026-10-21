<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini, through the generateContent REST endpoint (Google publishes no PHP SDK).
 * Model turns are stored as returned, thought signatures included, as the API requires
 * them back for function calling.
 */
class GeminiModel implements AssistantModel
{
    private const REFUSALS = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION', 'IMAGE_SAFETY'];

    public function label(): string
    {
        return 'Gemini';
    }

    public function configured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function userMessage(string $text): array
    {
        return ['role' => 'user', 'parts' => [['text' => $text]]];
    }

    public function call(string $system, array $messages, array $tools): AssistantTurn
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                // Stays under the web server's one-minute limit, one model call per request.
                ->timeout(45)->acceptJson()
                // Overloaded servers (common on the free tier) answer "busy" at once and usually accept a retry.
                ->retry(3, fn (int $attempt): int => $attempt * 2000, fn (\Throwable $e): bool => $e instanceof RequestException && in_array($e->response->status(), [500, 502, 503, 504], true), throw: false)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('services.gemini.model').':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => $this->replayable($messages),
                    'tools' => [['functionDeclarations' => array_map(fn (array $tool): array => [
                        'name' => $tool['name'], 'description' => $tool['description'],
                        'parameters' => $this->schema(['type' => 'object', 'properties' => $tool['properties'], 'required' => $tool['required']]),
                    ], $tools)]],
                    'generationConfig' => ['maxOutputTokens' => 16000],
                ]);
        } catch (ConnectionException $e) {
            report($e);

            throw new AssistantModelException(str_contains($e->getMessage(), 'timed out')
                ? 'Gemini a mis trop de temps à répondre. Renvoyez votre message (ou découpez la demande en plus petites étapes).'
                : 'Impossible de joindre Gemini. Vérifiez la connexion internet du serveur.');
        }

        if ($response->failed()) {
            $status = (string) $response->json('error.status');
            throw new AssistantModelException(match (true) {
                $response->status() === 429 => $this->quotaMessage($response->json('error.details') ?? []),
                in_array($status, ['PERMISSION_DENIED', 'UNAUTHENTICATED'], true) || str_contains((string) $response->json('error.message'), 'API key') => 'La clé API Gemini est refusée. Vérifiez GEMINI_API_KEY dans le fichier .env.',
                in_array($response->status(), [500, 502, 503, 504], true) => 'Les serveurs de Gemini sont surchargés en ce moment (fréquent sur l’offre gratuite). Renvoyez votre message dans une minute.',
                $response->status() === 404 => 'Le modèle Gemini « '.config('services.gemini.model').' » est introuvable. Vérifiez CODENYR_GEMINI_MODEL dans le fichier .env.',
                default => tap('Gemini n’a pas pu répondre (erreur '.$response->status().'). Réessayez dans un instant.', fn () => report(new AssistantModelException('Gemini '.$response->status().' : '.$response->json('error.message')))),
            });
        }

        $candidate = $response->json('candidates.0');
        $usage = $response->json('usageMetadata') ?? [];
        $cached = (int) ($usage['cachedContentTokenCount'] ?? 0);
        $input = max(0, (int) ($usage['promptTokenCount'] ?? 0) - $cached);
        $output = (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0);
        if (! is_array($candidate) || ! isset($candidate['content']['parts'])) {
            // Blocked prompt, or a candidate stopped before producing anything.
            $reason = $candidate['finishReason'] ?? $response->json('promptFeedback.blockReason');

            return new AssistantTurn(message: [], texts: [], toolCalls: [], stop: $reason === 'MAX_TOKENS' ? 'max_tokens' : 'refusal', inputTokens: $input, outputTokens: $output, cacheReadTokens: $cached);
        }
        if (($candidate['finishReason'] ?? '') === 'MALFORMED_FUNCTION_CALL') {
            throw new AssistantModelException('Gemini a mal formulé une action. Renvoyez votre message ou reformulez-le.');
        }

        $texts = $calls = [];
        foreach ($candidate['content']['parts'] as $part) {
            if (isset($part['functionCall'])) {
                $calls[] = ['id' => (string) ($part['functionCall']['id'] ?? ''), 'name' => (string) $part['functionCall']['name'], 'input' => (array) ($part['functionCall']['args'] ?? [])];
            } elseif (isset($part['text']) && empty($part['thought']) && trim($part['text']) !== '') {
                $texts[] = $part['text'];
            }
        }

        return new AssistantTurn(
            message: ['role' => 'model', 'parts' => $candidate['content']['parts']],
            texts: $texts,
            toolCalls: $calls,
            stop: match (true) {
                $calls !== [] => 'tool_use',
                in_array($candidate['finishReason'] ?? '', self::REFUSALS, true) => 'refusal',
                ($candidate['finishReason'] ?? '') === 'MAX_TOKENS' => 'max_tokens',
                default => 'end',
            },
            inputTokens: $input,
            outputTokens: $output,
            cacheReadTokens: $cached,
        );
    }

    public function toolResults(array $results): array
    {
        return ['role' => 'user', 'parts' => array_map(function (array $result): array {
            $decoded = json_decode($result['content'], true);
            // A function response must be an object.
            $response = $result['is_error'] ? ['error' => $result['content']] : (is_array($decoded) && ! array_is_list($decoded) ? $decoded : ['result' => $decoded ?? $result['content']]);

            return ['functionResponse' => array_filter(['id' => $result['id'], 'name' => $result['name'], 'response' => $response], fn ($value) => $value !== '')];
        }, $results)];
    }

    public function prices(): array
    {
        return config('services.gemini.prices');
    }

    /**
     * The free tier has a small daily request quota per model: say which limit was hit and when it resets.
     *
     * @param  array<int, array<string, mixed>>  $details
     */
    private function quotaMessage(array $details): string
    {
        $violation = collect($details)->pluck('violations')->flatten(1)->first();
        $delay = (int) collect($details)->pluck('retryDelay')->filter()->first();
        if (is_array($violation) && str_contains((string) ($violation['quotaId'] ?? ''), 'PerDay')) {
            return 'Quota gratuit du jour atteint pour ce modèle Gemini ('.($violation['quotaValue'] ?? '?').' requêtes ; chaque étape de l’assistant en compte une). '
                .($delay > 0 ? 'Il se recharge dans environ '.max(1, intdiv($delay, 3600)).' h. ' : '')
                .'En attendant, changez CODENYR_GEMINI_MODEL dans le .env (par exemple gemini-flash-lite-latest), ou passez sur Claude.';
        }

        return 'Trop de demandes envoyées à Gemini en une minute (limite de l’offre gratuite). Renvoyez votre message dans une minute.';
    }

    /**
     * Drops empty "required" lists, which the Gemini schema subset does not need.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function schema(array $schema): array
    {
        if (($schema['required'] ?? null) === []) {
            unset($schema['required']);
        }
        foreach ($schema['properties'] ?? [] as $key => $property) {
            $schema['properties'][$key] = $this->schema($property);
        }
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->schema($schema['items']);
        }

        return $schema;
    }

    /**
     * Arguments and responses went through json_decode(…, true): an empty object became []
     * and must be sent back as {}.
     *
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    private function replayable(array $messages): array
    {
        foreach ($messages as &$message) {
            foreach ($message['parts'] ?? [] as $index => $part) {
                if (isset($part['functionCall']) && ($part['functionCall']['args'] ?? []) === []) {
                    $message['parts'][$index]['functionCall']['args'] = new \stdClass;
                }
                if (isset($part['functionResponse']) && ($part['functionResponse']['response'] ?? []) === []) {
                    $message['parts'][$index]['functionResponse']['response'] = new \stdClass;
                }
            }
        }

        return $messages;
    }
}
