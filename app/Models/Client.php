<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $guarded = ['id'];

    /** @return HasMany<ClientProject, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(ClientProject::class);
    }
}
