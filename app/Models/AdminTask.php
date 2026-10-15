<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminTask extends Model
{
    public const CATEGORIES = ['Administratif', 'Juridique', 'Mise en ligne', 'Autre'];

    /** Conditions that complete a task on their own. */
    public const AUTO_CHECKS = ['legal_siret', 'legal_mediator', 'test_admin_removed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }

    public function autoDone(): bool
    {
        return match ($this->auto_check) {
            'legal_siret' => filled(config('codenyr.legal.siret')),
            'legal_mediator' => filled(config('codenyr.legal.mediator')),
            'test_admin_removed' => ! User::where('email', 'admin-test@codenyr.test')->exists(),
            default => false,
        };
    }

    public function isDone(): bool
    {
        return $this->done_at !== null || $this->autoDone();
    }

    /** Marks tasks whose automatic condition is now met, so counters stay accurate everywhere. */
    public static function syncAutomatic(): void
    {
        static::whereNull('done_at')->whereNotNull('auto_check')->get()
            ->filter(fn (self $task) => $task->autoDone())
            ->each(fn (self $task) => $task->update(['done_at' => now()]));
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('done_at');
    }
}
