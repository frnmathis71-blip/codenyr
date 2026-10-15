<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Lead extends Model
{
    public const STATUSES = ['new' => 'Nouveau', 'contacted' => 'Contacté', 'meeting' => 'Rendez-vous', 'quote_sent' => 'Devis envoyé', 'accepted' => 'Accepté', 'refused' => 'Refusé'];

    protected $guarded = ['id'];

    /**
     * Open (non-archived) leads per status, computed once per request so the
     * dashboard and the admin sidebar badge share a single aggregate query.
     *
     * @return Collection<string, int>
     */
    public static function openStatusCounts(): Collection
    {
        $attributes = request()->attributes;

        if (! $attributes->has('lead_status_counts')) {
            $attributes->set('lead_status_counts', collect(self::STATUSES)->map(fn () => 0)->merge(
                self::whereNull('archived_at')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
            ));
        }

        return $attributes->get('lead_status_counts');
    }

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
