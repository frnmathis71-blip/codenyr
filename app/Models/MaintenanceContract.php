<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/** @property CarbonInterface $starts_on */
class MaintenanceContract extends Model
{
    public const STATUSES = ['draft' => 'Brouillon', 'active' => 'Active', 'suspended' => 'Suspendue', 'terminated' => 'Résiliée', 'archived' => 'Archivée'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'amount_cents' => 'integer'];
    }
}
