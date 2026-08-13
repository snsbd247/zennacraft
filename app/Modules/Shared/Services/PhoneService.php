<?php

namespace App\Modules\Shared\Services;

use Illuminate\Database\Eloquent\Builder;

class PhoneService
{
    public function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $phone)) ?: '';

        if (str_starts_with($digits, '8801') && strlen($digits) === 13) {
            return '0'.substr($digits, 3);
        }

        return $digits;
    }

    public function hash(?string $phone): string
    {
        $normalized = $this->normalize($phone);

        return $normalized === '' ? '' : hash('sha256', $normalized);
    }

    public function lookupValues(?string $phone): array
    {
        $normalized = $this->normalize($phone);

        if ($normalized === '') {
            return [];
        }

        $values = [$normalized];

        if (preg_match('/^01[3-9]\d{8}$/', $normalized) === 1) {
            $values[] = '880'.substr($normalized, 1);
        }

        return array_values(array_unique($values));
    }

    /**
     * The single, canonical query-level phone match: builds a whereRaw
     * matching a stripped (spaces/dashes/plus removed) DB column against
     * every normalized form lookupValues() considers equivalent (e.g. both
     * '01712345678' and '8801712345678'). Previously duplicated
     * byte-for-byte across 8 classes (OrderSecurityService, CouponService,
     * RecoveryService, CustomerFraudService, CheckoutService,
     * CustomerIntelligenceService, CustomerAuthController,
     * CustomerAuthApiController) — a single edit to one copy that missed
     * the other 7 would have made those checks silently disagree about
     * whether two phone numbers are the same number.
     */
    public function whereNormalizedPhone(Builder $query, string $column, string $phone): void
    {
        $values = $this->lookupValues($phone);

        if ($values === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $expression = "REPLACE(REPLACE(REPLACE(TRIM({$column}), ' ', ''), '-', ''), '+', '')";

        $query->whereRaw($expression.' in ('.$placeholders.')', $values);
    }

    public function isValidBangladeshMobile(?string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', trim((string) $phone)) ?: '';
        $normalized = $this->normalize($phone);

        if ($digits === '' || strlen($digits) < 10) {
            return false;
        }

        if (preg_match('/^(\d)\1{9,}$/', $digits) === 1) {
            return false;
        }

        if (in_array($digits, ['1234567890', '12345678901'], true)) {
            return false;
        }

        return preg_match('/^01[3-9]\d{8}$/', $normalized) === 1;
    }
}
