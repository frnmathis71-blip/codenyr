<?php

namespace App\Models;

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
            'validity_days' => 30, 'payment_days' => 30, 'deposit_percent' => '40', 'quote_followup_days' => 7, 'delivery_alert_days' => 3, 'quote_mention' => '', 'invoice_mention' => '', 'footer' => '',
        ], $stored ? $stored->values : []);
    }
}
