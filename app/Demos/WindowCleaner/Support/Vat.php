<?php

namespace App\Demos\WindowCleaner\Support;

use Money\Money;

/**
 * VAT arithmetic for the demo's tax-inclusive consumer prices.
 */
final class Vat
{
    /**
     * Split a VAT-inclusive gross into net + VAT.
     *
     * VAT is extracted from the gross at rate/(100 + rate), rounded
     * DOWN to the penny, and net is whatever remains — so net + vat
     * always reassembles the exact gross, and no penny is ever
     * created or lost (try £14.99).
     *
     * @return array{net: Money, vat: Money}
     */
    public static function split(Money $gross): array
    {
        $rate = (int) config('demo.vat_rate_percent');

        $vat = $gross->multiply($rate)->divide(100 + $rate, Money::ROUND_DOWN);

        return ['net' => $gross->subtract($vat), 'vat' => $vat];
    }
}
