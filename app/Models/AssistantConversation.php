<?php

namespace App\Models;

use App\Services\AdminAssistant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int, array<string, mixed>> $messages
 * @property array<int, array<string, mixed>> $log
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_tokens
 * @property int $cache_write_tokens
 * @property string $agent
 * @property string $provider
 * @property string $status
 * @property int $steps
 * @property array{calls: list<array{id: string, name: string, input: array<string, mixed>}>, results: list<array{id: string, name: string, content: string, is_error: bool}>, index: int}|null $pending
 * @property int|null $waiting_on
 */
class AssistantConversation extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['messages' => '[]', 'log' => '[]'];

    protected function casts(): array
    {
        return ['messages' => 'array', 'log' => 'array', 'pending' => 'array', 'steps' => 'integer'];
    }

    public function running(): bool
    {
        return $this->status === 'running';
    }

    /** Estimated API cost in dollars, from the token counts and the configured prices. */
    public function costDollars(): float
    {
        $prices = AdminAssistant::model($this->provider)->prices();

        return ($this->input_tokens * $prices['input'] + $this->output_tokens * $prices['output']
            + $this->cache_read_tokens * $prices['cache_read'] + $this->cache_write_tokens * $prices['cache_write']) / 1_000_000;
    }
}
