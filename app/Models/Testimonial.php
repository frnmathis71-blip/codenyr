<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    public const STATUSES = ['draft' => 'Brouillon', 'pending' => 'À modérer', 'approved' => 'Publié', 'rejected' => 'Refusé'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'rating' => 'integer', 'revision' => 'integer', 'consented_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
