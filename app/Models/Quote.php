<?php

namespace App\Models;

class Quote extends BillingRecord
{
    public const STATUSES = ['draft' => 'Brouillon', 'ready' => 'Prêt', 'sent' => 'Envoyé', 'accepted' => 'Accepté', 'refused' => 'Refusé', 'expired' => 'Expiré', 'cancelled' => 'Annulé'];
}
