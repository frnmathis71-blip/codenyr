<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProjectEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoicePayments
{
    /** @param array<string, mixed> $data */
    public function record(int $invoiceId, array $data, string $key): void
    {
        Gate::authorize('admin');
        Validator::make(['payment' => $data], ['payment.amount' => 'required|string', 'payment.paid_on' => 'required|date|before_or_equal:today', 'payment.method' => ['required', Rule::in(array_keys(Payment::METHODS))], 'payment.reference' => 'nullable|string|max:255', 'payment.comment' => 'nullable|string|max:5000'])->validate();
        $amount = CommercialCalculator::decimal($data['amount']);
        DB::transaction(function () use ($invoiceId, $data, $key, $amount): void {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoiceId);
            if (Payment::where('submission_key', $key)->exists()) {
                return;
            }
            if (! $invoice->finalized_at || $invoice->archived_at || $invoice->project->archived_at) {
                throw ValidationException::withMessages(['payment.amount' => 'Émettez la facture et restaurez le dossier avant de saisir un règlement.']);
            }
            if ($amount <= 0 || $amount > $invoice->remainingCents()) {
                throw ValidationException::withMessages(['payment.amount' => 'Le paiement doit être positif et inférieur ou égal au reste dû.']);
            }
            Payment::create(['invoice_id' => $invoice->id, 'amount_cents' => $amount, 'paid_on' => $data['paid_on'], 'method' => $data['method'], 'reference' => $data['reference'] ?? '', 'comment' => $data['comment'] ?? '', 'submission_key' => $key, 'user_id' => auth()->id()]);
            ProjectEvent::record($invoice->client_project_id, 'Paiement de '.CommercialCalculator::euros($amount).' enregistré sur '.$invoice->number);
        });
    }

    public function void(int $invoiceId, int $paymentId, string $reason): void
    {
        Gate::authorize('admin');
        Validator::make(['correctionReason' => trim($reason)], ['correctionReason' => 'required|string|min:5|max:1000'], ['correctionReason.required' => 'Indiquez le motif de la correction.', 'correctionReason.min' => 'Précisez le motif de la correction (5 caractères minimum).'])->validate();
        DB::transaction(function () use ($invoiceId, $paymentId, $reason): void {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->archived_at || $invoice->project->archived_at) {
                throw ValidationException::withMessages(['correctionReason' => 'Restaurez le dossier et la facture avant de corriger un règlement.']);
            }
            $payment = Payment::where('invoice_id', $invoice->id)->lockForUpdate()->findOrFail($paymentId);
            if ($payment->voided_at) {
                return;
            }
            $payment->update(['voided_at' => now(), 'void_reason' => trim($reason)]);
            ProjectEvent::record($invoice->client_project_id, 'Saisie du paiement #'.$payment->id.' annulée sur '.$invoice->number.' : '.trim($reason));
        });
    }
}
