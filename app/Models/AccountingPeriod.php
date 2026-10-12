<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed> $settings */
class AccountingPeriod extends Model
{
    protected $fillable = ['month', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
