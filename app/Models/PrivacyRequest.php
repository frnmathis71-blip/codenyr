<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyRequest extends Model
{
    public const TYPES = ['access' => 'Accès / copie de mes données', 'rectification' => 'Rectification', 'erasure' => 'Effacement', 'restriction' => 'Limitation', 'objection' => 'Opposition', 'portability' => 'Portabilité'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
