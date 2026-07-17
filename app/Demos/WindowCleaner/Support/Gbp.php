<?php

namespace App\Demos\WindowCleaner\Support;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;

/**
 * GBP display, parsing, and VAT arithmetic for the demo.
 *
 * The package stores integer minor units and exposes Money values;
 * this helper is the only place the demo converts to and from the
 * strings that forms and pages use.
 */
final class Gbp
{
    public static function money(int $minorUnits): Money
    {
        return new Money($minorUnits, new Currency('GBP'));
    }

    public static function format(Money $money): string
    {
        $decimal = (new DecimalMoneyFormatter(new ISOCurrencies))->format($money->absolute());
        $sign = $money->isNegative() ? '-' : '';

        return $money->getCurrency()->getCode() === 'GBP'
            ? "{$sign}£{$decimal}"
            : "{$sign}{$money->getCurrency()->getCode()} {$decimal}";
    }

    public static function parse(string $decimal): Money
    {
        return (new DecimalMoneyParser(new ISOCurrencies))->parse($decimal, new Currency('GBP'));
    }

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
    public static function vatSplit(Money $gross): array
    {
        $rate = (int) config('demo.vat_rate_percent');

        $vat = $gross->multiply($rate)->divide(100 + $rate, Money::ROUND_DOWN);

        return ['net' => $gross->subtract($vat), 'vat' => $vat];
    }
}
