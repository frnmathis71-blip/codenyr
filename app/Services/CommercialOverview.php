<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\Invoice;
use App\Models\MaintenanceContract;
use App\Models\Quote;

class CommercialOverview
{
    /** @return array<string, mixed> */
    public function data(): array
    {
        $projects = ClientProject::whereNull('archived_at')->whereNotIn('status', ['completed', 'cancelled'])->with(['quotes', 'invoices.payments', 'documents', 'maintenance'])->get();
        $actions = [];
        $missing = 0;
        foreach ($projects as $project) {
            foreach ($project->alerts() as $alert) {
                $actions[] = ['project_id' => $project->id, 'name' => $project->name, 'alert' => $alert];
            }
            if ($project->quotes->contains('status', 'accepted')) {
                foreach ($project->checklist() as $label => $present) {
                    if (! $present && in_array($label, ['CGV signées', 'Contrat signé', 'Maintenance signée'], true)) {
                        $missing++;
                    }
                }
            }
        }

        return ['active' => $projects->count(), 'pending_quotes' => Quote::where('status', 'sent')->whereNull('archived_at')->count(), 'unpaid' => Invoice::whereNotNull('finalized_at')->with('payments')->get()->filter(fn (Invoice $invoice): bool => $invoice->remainingCents() > 0)->count(), 'missing' => $missing, 'maintenance' => MaintenanceContract::where('status', 'active')->count(), 'actions' => array_slice($actions, 0, 12)];
    }
}
