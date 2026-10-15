<?php

namespace App\Models;

use App\Services\CommercialConfiguration;
use App\Services\LegalDocumentRules;
use App\Services\StandardBusinessProfile;
use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed> $values */
class CommercialSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    /** @return array<string, mixed> */
    public static function current(): array
    {
        $stored = static::find(1);

        return array_replace([
            'name' => 'Codenyr', 'legal_name' => '', 'address' => '', 'phone' => '', 'email' => config('codenyr.email'), 'registration' => '', 'logo_path' => '',
            'vat_enabled' => false, 'vat_rate' => '20', 'tax_mention' => '', 'quote_prefix' => 'DEV', 'invoice_prefix' => 'FAC',
            'validity_days' => 30, 'payment_days' => 30, 'deposit_percent' => '40', 'quote_followup_days' => 7, 'delivery_alert_days' => 3, 'external_fees_mention' => 'Les frais liés aux services tiers, licences, noms de domaine, API ou plateformes externes ne sont inclus que lorsqu’ils sont explicitement indiqués dans le devis.', 'quote_mention' => '', 'invoice_mention' => '', 'footer' => '',
        ], CommercialConfiguration::defaults(), LegalDocumentRules::defaults(), StandardBusinessProfile::values(),
            // Saved settings win, but a field left empty keeps the standard value instead of blocking documents.
            array_filter($stored ? $stored->values : [], fn ($value): bool => filled($value) || is_bool($value)));
    }
}
