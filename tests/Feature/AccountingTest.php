<?php

use App\Livewire\Admin\Accounting;
use App\Models\AccountingPeriod;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\MonthlyAccounting;
use App\Services\UrssafCalculator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function accountingInvoice(int $ht = 100000, int $vat = 20000, bool $finalized = true): Invoice
{
    $client = Client::create(['name' => 'Client comptabilité', 'email' => 'comptabilite@example.test']);
    $project = ClientProject::create(['client_id' => $client->id, 'name' => 'Projet comptabilité', 'type' => 'Site vitrine', 'status' => 'development']);

    return Invoice::create(['client_project_id' => $project->id, 'number' => 'FAC-'.Str::uuid(), 'issued_on' => '2026-01-01', 'due_on' => '2026-01-31', 'items' => [], 'snapshot' => ['client' => ['name' => $client->name]], 'totals' => ['initial' => ['ht' => $ht, 'vat' => $vat, 'ttc' => $ht + $vat]], 'finalized_at' => $finalized ? now() : null]);
}

function accountingPayment(Invoice $invoice, int $cents, string $date, bool $void = false): Payment
{
    return Payment::create(['invoice_id' => $invoice->id, 'amount_cents' => $cents, 'paid_on' => $date, 'method' => 'transfer', 'submission_key' => (string) Str::uuid(), 'voided_at' => $void ? now() : null]);
}

test('accounting page and livewire actions are restricted to administrators', function () {
    $this->get('/admin/comptabilite')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin/comptabilite')->assertForbidden();
    Livewire::test(Accounting::class)->assertForbidden();
    $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/comptabilite')->assertOk()->assertSee('Comptabilité et URSSAF');
});

test('monthly accounting uses payment dates excludes voids and retains archived receipts', function () {
    $invoice = accountingInvoice();
    accountingPayment($invoice, 30000, '2026-01-31');
    accountingPayment($invoice, 60000, '2026-02-01');
    accountingPayment($invoice, 30000, '2026-03-01');
    accountingPayment($invoice, 99999, '2026-02-10', true);
    accountingInvoice(); // An unpaid issued invoice must not contribute.
    $invoice->update(['archived_at' => now()]);
    $invoice->project->update(['archived_at' => now()]);
    $report = app(MonthlyAccounting::class)->report('2026-02');
    expect($report['ht'])->toBe(50000)->and($report['ttc'])->toBe(60000)->and($report['vat'])->toBe(10000)
        ->and($report['year_ht'])->toBe(75000)->and($report['rows'])->toHaveCount(1)->and($report['warnings'])->toBe([]);
});

test('partial payments preserve the last HT cent across year boundaries', function () {
    $invoice = accountingInvoice(100, 20);
    accountingPayment($invoice, 40, '2025-12-31');
    accountingPayment($invoice, 40, '2026-01-01');
    accountingPayment($invoice, 40, '2026-02-01');
    $service = app(MonthlyAccounting::class);
    expect($service->report('2025-12')['ht'])->toBe(33)
        ->and($service->report('2026-01')['ht'])->toBe(34)
        ->and($service->report('2026-02')['ht'])->toBe(33)
        ->and($service->report('2026-02')['year_ht'])->toBe(67);
});

test('payments without VAT are fully counted and cancelled payments are recalculated', function () {
    $invoice = accountingInvoice(10000, 0);
    $payment = accountingPayment($invoice, 10000, '2026-02-01');
    $service = app(MonthlyAccounting::class);
    expect($service->report('2026-02')['ht'])->toBe(10000);
    $payment->update(['voided_at' => now(), 'void_reason' => 'Erreur de saisie']);
    expect($service->report('2026-02')['ht'])->toBe(0);
});

test('invalid receipts are flagged and block the CSV instead of silently producing a complete report', function () {
    $invoice = accountingInvoice(finalized: false);
    accountingPayment($invoice, 5000, '2026-02-01');
    expect(app(MonthlyAccounting::class)->report('2026-02')['warnings'])->toHaveCount(1);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Accounting::class)->set('month', '2026-02')->call('export')->assertHasErrors('accounting')->assertSee('Récapitulatif incomplet');
});

test('overpayments are flagged', function () {
    $invoice = accountingInvoice(100, 0);
    accountingPayment($invoice, 101, '2026-02-01');
    $report = app(MonthlyAccounting::class)->report('2026-02');
    expect($report['warnings'])->toHaveCount(1)->and($report['ht'])->toBe(0);
});

test('BNC options calculate social contributions training and optional income tax separately', function () {
    $calculator = app(UrssafCalculator::class);
    $options = ['activity' => 'bnc'];
    expect($calculator->estimate(100000, $options, '2026-10')['total'])->toBe(25800);
    $estimate = $calculator->estimate(100000, $options + ['acre' => true, 'liberatory' => true], '2026-10');
    expect($estimate['amounts'])->toBe(['social' => 19200, 'training' => 200, 'tax' => 2200, 'other' => 0])
        ->and($estimate['total'])->toBe(21600)->and($estimate['remaining'])->toBe(78400);
});

test('ACRE rates preserve official rounding for sales and leave CFP and income tax unchanged', function () {
    $estimate = app(UrssafCalculator::class)->estimate(100000, ['activity' => 'sales', 'acre' => true, 'acre_scheme' => '25', 'liberatory' => true], '2026-10');
    expect($estimate['rates'])->toBe(['social' => 930, 'training' => 10, 'tax' => 100, 'other' => 0]);
});

test('custom rates are effective rates without double applying ACRE and work outside 2026', function () {
    $estimate = app(UrssafCalculator::class)->estimate(100000, ['activity' => 'bnc', 'acre' => true, 'liberatory' => true, 'custom' => true, 'social_rate' => '12,5', 'training_rate' => '0,2', 'tax_rate' => '2,2', 'other_rate' => '0,1'], '2027-01');
    expect($estimate['total'])->toBe(15000)->and($estimate['rates']['social'])->toBe(1250);
});

test('zero revenue means zero estimates', function () {
    expect(app(UrssafCalculator::class)->estimate(0, ['activity' => 'bnc'], '2026-10')['total'])->toBe(0);
});

test('unverified years and invalid rates cannot be saved', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Accounting::class)->set('month', '2027-01')->set('options.activity', 'bnc')->call('saveSettings')->assertHasErrors('custom');
    Livewire::test(Accounting::class)->set('month', '2026-10')->set('options.activity', 'bnc')->set('options.other_rate', '-1')->call('saveSettings')->assertHasErrors('other_rate');
    expect(AccountingPeriod::count())->toBe(0);
});

test('monthly choices persist independently and ACRE is not silently renewed', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Accounting::class)->set('month', '2026-09')->set('options.activity', 'bnc')->set('options.acre', true)->set('options.liberatory', true)->call('saveSettings')->assertHasNoErrors()
        ->set('month', '2026-10')->assertSet('options.activity', 'bnc')->assertSet('options.acre', false)->assertSet('options.liberatory', true)
        ->set('options.activity', 'bic_services')->call('saveSettings')->assertHasNoErrors()
        ->set('month', '2026-09')->assertSet('options.activity', 'bnc')->assertSet('options.acre', true);
    expect(AccountingPeriod::count())->toBe(2);
});

test('simulation never adds or edits real accounting receipts', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Accounting::class)->set('month', '2026-10')->set('options.activity', 'bnc')->set('simulation', true)->set('simulatedRevenue', '1000')
        ->assertSee('258,00 €')->assertSee('Aucun encaissement enregistré');
    expect(Payment::count())->toBe(0)->and(Invoice::count())->toBe(0)->and(AccountingPeriod::count())->toBe(0);
});

test('accounting CSV uses real receipts and is downloadable', function () {
    accountingPayment(accountingInvoice(), 120000, '2026-10-02');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Accounting::class)->set('month', '2026-10')->call('export')->assertFileDownloaded('encaissements-2026-10.csv');
});

test('invalid months are rejected before querying accounting data', function () {
    expect(fn () => app(MonthlyAccounting::class)->report('2026-13'))->toThrow(ValidationException::class);
});

test('custom ACRE rates are not carried into a later month as ordinary social rates', function () {
    AccountingPeriod::create(['month' => '2026-09', 'settings' => ['activity' => 'bnc', 'acre' => true, 'custom' => true, 'social_rate' => '12.8', 'training_rate' => '0.2']]);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Accounting::class)->set('month', '2026-10')->assertSet('options.acre', false)->assertSet('options.custom', false)->assertSet('options.social_rate', '');
});

test('Cipav ACRE needs an explicit rate and the new ACRE scheme cannot apply before July', function () {
    $calculator = app(UrssafCalculator::class);
    expect(fn () => $calculator->estimate(100000, ['activity' => 'cipav', 'acre' => true], '2026-10'))->toThrow(ValidationException::class);
    expect(fn () => $calculator->estimate(100000, ['activity' => 'bnc', 'acre' => true, 'acre_scheme' => '25'], '2026-06'))->toThrow(ValidationException::class);
});

test('CSV exports neutralize formulas in payment references and retain exact monetary totals', function () {
    $payment = accountingPayment(accountingInvoice(), 120000, '2026-10-02');
    $payment->update(['reference' => '=1+1']);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $component = Livewire::test(Accounting::class)->set('month', '2026-10')->call('export')->assertFileDownloaded('encaissements-2026-10.csv');
    $csv = base64_decode($component->effects['download']['content']);
    expect($csv)->toContain("'=1+1", 'TOTAL;;;;;1200.00;200.00;1000.00');
});
