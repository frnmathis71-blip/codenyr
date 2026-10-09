<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed>|null $parameters */
class DocumentTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['parameters' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (DocumentTemplate $template): void {
            if ($template->type === 'terms') {
                $template->parameters = collect(CommercialSetting::current())->filter(fn ($value, $key) => str_starts_with($key, 'clause_') || in_array($key, ['extra_hourly_rate', 'validity_days', 'external_fees_mention']))->all();
            }
        });
        static::updating(function (DocumentTemplate $template): void {
            if ($template->isDirty(['content', 'type', 'name', 'revision', 'version_label', 'effective_on', 'parameters'])) {
                throw new \LogicException('Créez une nouvelle version du modèle pour conserver les versions historiques.');
            }
        });
        static::deleting(function (): void {
            throw new \LogicException('Les versions des modèles doivent être conservées.');
        });
    }
}
