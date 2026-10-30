<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A business canvassed by phone, visit or mail, before it ever fills in the site's form. */
class Outreach extends Model
{
    public const STATUSES = ['todo' => 'À contacter', 'no_answer' => 'Pas de réponse', 'callback' => 'À rappeler', 'meeting' => 'Rendez-vous', 'quote_sent' => 'Devis envoyé', 'won' => 'Gagné', 'refused' => 'Refusé'];

    /** Statuses that end the canvassing: no follow-up is due any more. */
    public const CLOSED = ['won', 'refused'];

    public const CHANNELS = ['phone' => 'Téléphone', 'visit' => 'Visite', 'email' => 'E-mail', 'mail' => 'Courrier', 'sms' => 'SMS'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_contact_on' => 'date', 'next_action_on' => 'date'];
    }

    public function isDue(): bool
    {
        return $this->next_action_on !== null && ! $this->next_action_on->isFuture() && ! in_array($this->status, self::CLOSED);
    }

    /** Follow-ups planned for today or already late. */
    public function scopeDue(Builder $query): void
    {
        $query->whereNotNull('next_action_on')->whereDate('next_action_on', '<=', today())->whereNotIn('status', self::CLOSED);
    }

    public function offerLabel(): ?string
    {
        $offer = config('codenyr.offers.'.$this->offer);

        return $offer ? $offer['name'].' · '.$offer['price'].' €' : null;
    }
}
