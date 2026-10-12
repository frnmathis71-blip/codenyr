<?php

namespace App\Livewire\Admin;

use App\Models\AccountingPeriod;
use App\Services\CommercialCalculator;
use App\Services\MonthlyAccounting;
use App\Services\UrssafCalculator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Accounting extends AdminComponent
{
    public string $month = '';

    /** @var array<string, mixed> */
    public array $options = [];

    public bool $simulation = false;

    public string $simulatedRevenue = '1000';

    public function mount(): void
    {
        $this->month = now('Europe/Paris')->format('Y-m');
        $this->loadSettings();
    }

    public function updatedMonth(): void
    {
        $this->resetValidation();
        if (preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $this->month)) {
            $this->loadSettings();
        }
    }

    private function loadSettings(): void
    {
        $saved = AccountingPeriod::where('month', $this->month)->first();
        $previous = $saved ?? AccountingPeriod::where('month', '<', $this->month)->orderByDesc('month')->first();
        $this->options = array_replace(app(UrssafCalculator::class)->defaults(), $previous->settings ?? []);
        // ACRE is time-limited: never assume that last month's entitlement still applies.
        if (! $saved) {
            $this->options['acre'] = false;
            if ($previous && ($previous->settings['acre'] ?? false) && ($previous->settings['custom'] ?? false)) {
                $this->options['custom'] = false;
                foreach (['social_rate', 'training_rate', 'tax_rate'] as $key) {
                    $this->options[$key] = '';
                }
            }
            if (! $previous && $this->month < '2026-07') {
                $this->options['acre_scheme'] = '50';
            }
        }
    }

    public function saveSettings(): void
    {
        $calculator = app(UrssafCalculator::class);
        $data = $calculator->settings($this->options, $this->month);
        $calculator->estimate(0, $data, $this->month);
        AccountingPeriod::updateOrCreate(['month' => $this->month], ['settings' => $data]);
        $this->options = $data;
        session()->flash('success', 'Options enregistrées pour ce mois. Aucune déclaration n’a été envoyée.');
    }

    public function export(): StreamedResponse
    {
        $report = app(MonthlyAccounting::class)->report($this->month);
        if ($report['warnings']) {
            throw ValidationException::withMessages(['accounting' => 'Corrigez les anomalies des encaissements avant d’exporter.']);
        }

        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Impossible de préparer l’export.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Date d’encaissement', 'Facture', 'Client', 'Mode', 'Référence', 'TTC EUR', 'TVA EUR', 'HT EUR'], ';', '"', '');
            foreach ($report['rows'] as $row) {
                $text = array_map(fn ($value): string => preg_match('/^[\s]*[=+\-@\t\r\n]/u', (string) $value) ? "'".$value : (string) $value, [$row['date'], $row['number'], $row['client'], $row['method'], $row['reference']]);
                fputcsv($stream, [...$text, CommercialCalculator::input($row['ttc']), CommercialCalculator::input($row['vat']), CommercialCalculator::input($row['ht'])], ';', '"', '');
            }
            fputcsv($stream, ['TOTAL', '', '', '', '', CommercialCalculator::input($report['ttc']), CommercialCalculator::input($report['vat']), CommercialCalculator::input($report['ht'])], ';', '"', '');
            fclose($stream);
        }, 'encaissements-'.$this->month.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function render(): View
    {
        $report = $estimate = $simulated = null;
        $guidance = [];
        try {
            $report = app(MonthlyAccounting::class)->report($this->month);
            $calculator = app(UrssafCalculator::class);
            if (! $report['warnings']) {
                $estimate = $calculator->estimate($report['ht'], $this->options, $this->month);
            }
            if ($this->simulation) {
                $cents = CommercialCalculator::decimal($this->simulatedRevenue);
                $simulated = $calculator->estimate($cents, $this->options, $this->month);
            }
        } catch (ValidationException $exception) {
            $guidance = array_merge(...array_values($exception->errors()));
        }

        return view('livewire.admin.accounting', compact('report', 'estimate', 'simulated', 'guidance') + ['profiles' => config('urssaf.profiles')])
            ->layout('components.admin-layout', ['title' => 'Comptabilité et URSSAF']);
    }
}
