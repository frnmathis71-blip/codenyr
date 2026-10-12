<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

class MonthlyAccounting
{
    public function monthStart(string $month): CarbonImmutable
    {
        Validator::make(['month' => $month], ['month' => ['required', 'regex:/^20\d{2}-(0[1-9]|1[0-2])$/']], ['month.regex' => 'Choisissez un mois valide entre 2000 et 2099.'])->validate();

        return CarbonImmutable::parse($month.'-01', 'Europe/Paris');
    }

    /** @return array<string, mixed> */
    public function report(string $month): array
    {
        $start = $this->monthStart($month);
        $end = $start->addMonth()->format('Y-m-d');
        $yearStart = $start->startOfYear()->format('Y-m-d');
        // Include historical instalments to allocate the exact remaining HT cent to the final payment.
        // Archived invoices and projects still contribute to receipts.
        $invoices = Invoice::whereHas('payments', fn ($query) => $query->whereNull('voided_at')->where('paid_on', '>=', $yearStart)->where('paid_on', '<', $end))
            ->with(['payments' => fn ($query) => $query->whereNull('voided_at')->where('paid_on', '<', $end)->orderBy('paid_on')->orderBy('id')])->get();
        $rows = $warnings = [];
        $yearHt = 0;
        foreach ($invoices as $invoice) {
            $totals = $invoice->totals['initial'] ?? [];
            $ht = $totals['ht'] ?? null;
            $ttc = $totals['ttc'] ?? null;
            $vat = $totals['vat'] ?? null;
            if (! $invoice->finalized_at || ! is_int($ht) || ! is_int($ttc) || ! is_int($vat) || $ht < 0 || $vat < 0 || $ttc <= 0 || $ttc > 999999999 || $ht + $vat !== $ttc
                || $invoice->payments->contains(fn (Payment $payment): bool => $payment->amount_cents <= 0) || $invoice->payments->sum('amount_cents') > $ttc) {
                $warnings[] = 'Facture '.($invoice->number ?: '#'.$invoice->id).' : encaissements ou totaux incohérents, ou facture non émise. Ses paiements sont exclus du calcul ; corrigez-les avant de déclarer.';

                continue;
            }
            $received = $allocatedHt = 0;
            foreach ($invoice->payments as $payment) {
                $received += $payment->amount_cents;
                $cumulativeHt = CommercialCalculator::rounded($received * $ht, $ttc);
                $paymentHt = $cumulativeHt - $allocatedHt;
                $allocatedHt = $cumulativeHt;
                $date = $payment->paid_on->format('Y-m-d');
                if ($date >= $yearStart) {
                    $yearHt += $paymentHt;
                }
                if (substr($date, 0, 7) !== $month) {
                    continue;
                }
                $rows[] = ['id' => $payment->id, 'invoice_id' => $invoice->id, 'number' => $invoice->number, 'client' => $invoice->snapshot['client']['name'] ?? 'Client', 'date' => $date,
                    'method' => Payment::METHODS[$payment->method] ?? $payment->method, 'reference' => $payment->reference ?? '',
                    'ttc' => $payment->amount_cents, 'ht' => $paymentHt, 'vat' => $payment->amount_cents - $paymentHt];
            }
        }
        usort($rows, fn (array $a, array $b): int => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);

        return ['month' => $month, 'rows' => $rows, 'warnings' => $warnings, 'ht' => array_sum(array_column($rows, 'ht')), 'ttc' => array_sum(array_column($rows, 'ttc')), 'vat' => array_sum(array_column($rows, 'vat')), 'year_ht' => $yearHt];
    }
}
