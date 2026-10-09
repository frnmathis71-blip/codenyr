<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $audience
 * @property string|null $company
 * @property string|null $country
 * @property string|null $vat_number
 * @property string|null $signatory
 * @property string|null $signatory_role
 */
class Client extends Model
{
    protected $guarded = ['id'];

    /** @return HasMany<ClientProject, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(ClientProject::class);
    }
}
