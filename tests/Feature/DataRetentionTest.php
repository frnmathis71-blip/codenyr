<?php

use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Lead;
use App\Models\User;

function retentionLead(array $attributes = [], int $yearsAgo = 4): Lead
{
    $lead = Lead::create(array_merge(['firstname' => 'Camille', 'lastname' => 'Test', 'email' => 'prospect@example.test', 'project_type' => 'Site Pro', 'description' => 'Une demande ancienne.'], $attributes));
    Lead::whereKey($lead->id)->update(['updated_at' => now()->subYears($yearsAgo)]);

    return $lead;
}

test('stale prospects are purged while recent leads and contract data are kept', function () {
    $stale = retentionLead();
    $recent = retentionLead(yearsAgo: 2);
    $customer = retentionLead(['user_id' => User::factory()->create()->id]);
    $withProject = retentionLead();
    $client = Client::create(['name' => 'Client', 'email' => 'client@example.test']);
    ClientProject::create(['client_id' => $client->id, 'lead_id' => $withProject->id, 'name' => 'Projet', 'type' => 'Site vitrine', 'status' => 'development']);

    $this->artisan('codenyr:purge-leads --dry-run')->expectsOutput('1 demande(s) à supprimer.')->assertSuccessful();
    expect(Lead::count())->toBe(4);

    $this->artisan('codenyr:purge-leads')->expectsOutput('1 demande(s) supprimée(s).')->assertSuccessful();
    expect(Lead::find($stale->id))->toBeNull()
        ->and(Lead::whereKey([$recent->id, $customer->id, $withProject->id])->count())->toBe(3);
});

test('privacy policy states retention periods', function () {
    $this->get('/politique-confidentialite')->assertOk()
        ->assertSee('trois ans après le dernier échange')
        ->assertSee('cinq ans après la fin de la prestation')
        ->assertSee('dix ans à compter de la clôture')
        ->assertSee('OVH SAS, qui assure l’hébergement du site et l’envoi des e-mails', false);
});
