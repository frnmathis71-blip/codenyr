<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Outreach;
use Illuminate\Console\Command;

class PurgeStaleLeads extends Command
{
    /** CNIL guideline: prospects are kept 3 years after the last contact. */
    public const RETENTION_YEARS = 3;

    protected $signature = 'codenyr:purge-leads {--dry-run : Afficher le nombre de demandes concernées sans rien supprimer}';

    protected $description = 'Supprimer les demandes de prospects sans suite depuis plus de trois ans';

    public function handle(): int
    {
        // Leads tied to a client account or a project are contract data and follow their own durations.
        $query = Lead::where('updated_at', '<', now()->subYears(self::RETENTION_YEARS))
            ->whereNull('user_id')
            ->whereNotExists(fn ($projects) => $projects->selectRaw('1')->from('client_projects')->whereColumn('client_projects.lead_id', 'leads.id'));

        // Canvassed businesses that never became clients follow the same rule.
        $outreaches = Outreach::where('updated_at', '<', now()->subYears(self::RETENTION_YEARS))->where('status', '!=', 'won');

        if ($this->option('dry-run')) {
            $this->info($query->count().' demande(s) à supprimer.');
            $this->info($outreaches->count().' fiche(s) de démarchage à supprimer.');

            return self::SUCCESS;
        }

        $this->info($query->delete().' demande(s) supprimée(s).');
        $this->info($outreaches->delete().' fiche(s) de démarchage supprimée(s).');

        return self::SUCCESS;
    }
}
