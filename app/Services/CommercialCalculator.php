<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

class CommercialCalculator
{
    public static function decimal(string|int $value): int
    {
        $value = str_replace([' ', "\u{00a0}", "\u{202f}", ','], ['', '', '', '.'], trim((string) $value));
        Validator::make(['amount' => $value], ['amount' => ['required', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/']])->validate();
        $parts = explode('.', $value);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }

    public static function input(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function rounded(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    /** @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $items, bool $vatEnabled): array
    {
        Validator::make(['items' => $items], [
            'items' => 'required|array|min:1|max:100', 'items.*.name' => 'required|string|max:200',
            'items.*.description' => 'nullable|string|max:5000', 'items.*.unit' => 'required|string|max:50',
            'items.*.price' => 'required|string', 'items.*.quantity' => 'required|string', 'items.*.discount' => 'required|string',
            'items.*.discount_type' => 'required|in:percent,fixed', 'items.*.vat' => 'required|string',
            'items.*.service_id' => 'nullable|integer|exists:services,id',
            'items.*.frequency' => 'required|in:once,monthly,quarterly,yearly',
            'items.*.optional' => 'required|boolean', 'items.*.selected' => 'required|boolean',
        ])->validate();
        $normalized = [];
        foreach ($items as $item) {
            $price = self::decimal($item['price']);
            $quantity = self::decimal($item['quantity']);
            $discount = self::decimal($item['discount']);
            $vat = self::decimal($item['vat']);
            Validator::make(compact('price', 'quantity', 'discount', 'vat'), ['price' => 'integer|between:0,99999999', 'quantity' => 'integer|between:1,1000000', 'discount' => 'integer|min:0|max:'.($item['discount_type'] === 'percent' ? '10000' : '99999999'), 'vat' => 'integer|between:0,10000'])->validate();
            $normalized[] = ['name' => $item['name'], 'description' => $item['description'] ?? '', 'unit' => $item['unit'], 'price_cents' => $price, 'quantity_units' => $quantity, 'discount_type' => $item['discount_type'], 'discount_value' => $discount, 'vat_basis' => $vatEnabled ? $vat : 0, 'frequency' => $item['frequency'], 'optional' => (bool) $item['optional'], 'selected' => (bool) $item['selected']];
            if (isset($item['service_id'])) {
                $normalized[array_key_last($normalized)]['service_id'] = (int) $item['service_id'];
            }
        }

        return $normalized;
    }

    /** @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function calculate(array $items, string $discountType = 'percent', int $discountValue = 0, string $depositType = 'none', int $depositValue = 0): array
    {
        Validator::make(compact('discountType', 'discountValue', 'depositType', 'depositValue'), ['discountType' => 'in:percent,fixed', 'discountValue' => 'integer|between:0,'.($discountType === 'percent' ? 10000 : 99999999), 'depositType' => 'in:none,percent,fixed', 'depositValue' => 'integer|between:0,'.($depositType === 'percent' ? 10000 : 99999999)])->validate();
        $groups = [];
        foreach (['once', 'monthly', 'quarterly', 'yearly'] as $frequency) {
            $rows = [];
            $subtotal = 0;
            $lineDiscounts = 0;
            foreach ($items as $index => $item) {
                if ($item['frequency'] !== $frequency || ($item['optional'] && ! $item['selected'])) {
                    continue;
                }
                $gross = self::rounded((int) $item['price_cents'] * (int) $item['quantity_units'], 100);
                if ($item['discount_type'] === 'fixed') {
                    Validator::make(['line_discount' => $item['discount_value']], ['line_discount' => 'integer|between:0,'.$gross])->validate();
                }
                $discount = $item['discount_type'] === 'percent' ? self::rounded($gross * (int) $item['discount_value'], 10000) : min($gross, (int) $item['discount_value']);
                $rows[$index] = ['net' => $gross - $discount, 'vat_basis' => $item['vat_basis']];
                $subtotal += $gross;
                $lineDiscounts += $discount;
            }
            $base = $subtotal - $lineDiscounts;
            // Bound products used in integer discount allocation below 64-bit overflow.
            Validator::make(['subtotal' => $subtotal], ['subtotal' => 'integer|between:0,999999999'])->validate();
            if ($discountType === 'fixed' && $frequency === 'once') {
                Validator::make(['global_discount' => $discountValue], ['global_discount' => 'integer|between:0,'.$base])->validate();
            }
            // A global fixed discount applies only to the initial payment.
            $global = $discountType === 'percent' ? self::rounded($base * $discountValue, 10000) : ($frequency === 'once' ? min($base, $discountValue) : 0);
            $remainingDiscount = $global;
            $remainingBase = $base;
            $buckets = [];
            foreach ($rows as $index => &$row) {
                $allocated = $remainingBase > 0 ? self::rounded($remainingDiscount * $row['net'], $remainingBase) : 0;
                $remainingBase -= $row['net'];
                $remainingDiscount -= $allocated;
                $row['net'] -= $allocated;
                $rate = $row['vat_basis'];
                $buckets[$rate] = ($buckets[$rate] ?? 0) + $row['net'];
            }
            unset($row);
            $tax = 0;
            foreach ($buckets as $rate => $net) {
                $tax += self::rounded($net * $rate, 10000);
            }
            $groups[$frequency] = ['subtotal' => $subtotal, 'discount' => $lineDiscounts + $global, 'ht' => $base - $global, 'vat' => $tax, 'ttc' => $base - $global + $tax, 'rows' => $rows, 'tax_buckets' => $buckets];
        }
        $initial = $groups['once'];
        $deposit = match ($depositType) {
            'percent' => self::rounded($initial['ttc'] * $depositValue, 10000), 'fixed' => $depositValue, default => 0
        };
        Validator::make(['deposit' => $deposit], ['deposit' => 'integer|between:0,'.$initial['ttc']])->validate();

        return ['initial' => $initial, 'recurring' => array_diff_key($groups, ['once' => true]), 'deposit' => $deposit, 'remaining' => $initial['ttc'] - $deposit];
    }
}
