<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    public const STATUSES = ['new' => 'Nouveau', 'contacted' => 'Contacté', 'meeting' => 'Rendez-vous', 'quote_sent' => 'Devis envoyé', 'accepted' => 'Accepté', 'refused' => 'Refusé'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['features' => 'array', 'delivered_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasOne<Testimonial, $this> */
    public function testimonial(): HasOne
    {
        return $this->hasOne(Testimonial::class);
    }
}
