<?php

use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Document;
use App\Models\Quote;
use App\Models\User;
use App\Services\CommercialBilling;
use App\Services\CommercialCalculator;
use App\Services\ProjectAutopilot;
use App\Support\TestMode;
use Illuminate\Support\Facades\Storage;

test('test mode issues incomplete documents and stamps every PDF', function () {
    Storage::fake('local');
    config(['codenyr.test_mode' => true, 'codenyr.legal.siret' => null]);
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $this->actingAs($admin);

    // A bare client: no address, no audience, no signatory.
    $project = ClientProject::create(['client_id' => Client::create(['name' => 'Client pressé', 'email' => 'presse@example.test'])->id, 'name' => 'Test rapide', 'type' => 'Landing Page', 'status' => 'quote_sent']);
    $calculator = app(CommercialCalculator::class);
    $items = $calculator->normalize([['name' => 'Landing', 'description' => '', 'price' => '490', 'quantity' => '1', 'discount' => '0', 'discount_type' => 'percent', 'vat' => '0', 'unit' => 'forfait', 'frequency' => 'once', 'optional' => false, 'selected' => true]], false);
    $quote = Quote::create(['client_project_id' => $project->id, 'issued_on' => today(), 'due_on' => today()->addDays(30), 'items' => $items, 'totals' => $calculator->calculate($items, 'percent', 0, 'percent', 4000), 'snapshot' => app(CommercialBilling::class)->snapshot($project), 'deposit_type' => 'percent', 'deposit_value' => 4000]);
    app(CommercialBilling::class)->finalize($quote);
    app(ProjectAutopilot::class)->answerQuote($quote, 'accepted');

    $result = app(ProjectAutopilot::class)->startKit($project->fresh());

    expect($result['missing'])->toBe([]);
    $contract = Document::where('client_project_id', $project->id)->where('type', 'contract')->sole();
    expect($contract->finalized_at)->not->toBeNull();
    expect(view('commercial.document-pdf', ['document' => $contract])->render())->toContain('MODE TEST');
    expect(view('commercial.billing-pdf', ['record' => $quote->refresh(), 'kind' => 'quote'])->render())->toContain('MODE TEST');
});

test('test mode is never active in production, even when forced', function () {
    config(['codenyr.test_mode' => true]);
    expect(TestMode::enabled())->toBeTrue();

    app()->detectEnvironment(fn () => 'production');
    expect(TestMode::enabled())->toBeFalse();
});

test('test mode is off in the test suite by default, so real rules stay covered', function () {
    expect(TestMode::enabled())->toBeFalse();
});
