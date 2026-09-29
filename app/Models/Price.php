<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'amount_cents'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }
}
