<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int, string>|null $technologies
 * @property array<int, string>|null $screenshots
 */
class Project extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'featured' => 'boolean', 'is_demo' => 'boolean', 'technologies' => 'array', 'screenshots' => 'array'];
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/'.$this->image).'?v='.$this->updated_at?->getTimestamp() : null;
    }
}
