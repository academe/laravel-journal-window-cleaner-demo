<?php

namespace App\Demos\WindowCleaner\Support;

use Academe\LaravelJournal\Models\Journal;
use Academe\LaravelJournal\Models\Ledger;
use App\Demos\WindowCleaner\Models\CompanyAccount;
use Money\Currency;
use Money\Money;

/**
 * Named lookups into the business's books (Level C), plus the one
 * monetary fact the whole app shares: its currency, read from
 * config('demo.currency') — GBP by default, switchable to USD.
 *
 * Five typed ledgers cover the accounting equation for this business:
 * Debtors + Bank (assets) = VAT owed (liability) + Sales (income)
 * − Expenses. Every customer journal lives in Debtors; the four company
 * account journals live in their own ledgers.
 */
final class Books
{
    public const SALES = 'Sales';

    public const VAT = 'VAT';

    public const BANK = 'Bank';

    public const EXPENSES = 'Expenses';

    public const LEDGER_DEBTORS = 'Debtors';

    public const LEDGER_BANK = 'Bank';

    public const LEDGER_SALES = 'Sales';

    public const LEDGER_VAT = 'VAT owed';

    public const LEDGER_EXPENSES = 'Expenses';

    public static function currencyCode(): string
    {
        return (string) config('demo.currency', 'GBP');
    }

    public static function currency(): Currency
    {
        return new Currency(self::currencyCode());
    }

    /**
     * Minor units in the demo currency, e.g. 1500 -> £15.00 / $15.00.
     */
    public static function money(int $minorUnits): Money
    {
        return new Money($minorUnits, self::currency());
    }

    public static function salesJournal(): Journal
    {
        return self::accountJournal(self::SALES);
    }

    public static function vatJournal(): Journal
    {
        return self::accountJournal(self::VAT);
    }

    public static function bankJournal(): Journal
    {
        return self::accountJournal(self::BANK);
    }

    public static function expensesJournal(): Journal
    {
        return self::accountJournal(self::EXPENSES);
    }

    public static function debtorsLedger(): Ledger
    {
        return self::ledger(self::LEDGER_DEBTORS);
    }

    public static function bankLedger(): Ledger
    {
        return self::ledger(self::LEDGER_BANK);
    }

    public static function salesLedger(): Ledger
    {
        return self::ledger(self::LEDGER_SALES);
    }

    public static function vatLedger(): Ledger
    {
        return self::ledger(self::LEDGER_VAT);
    }

    public static function expensesLedger(): Ledger
    {
        return self::ledger(self::LEDGER_EXPENSES);
    }

    private static function accountJournal(string $name): Journal
    {
        return CompanyAccount::where('name', $name)->firstOrFail()->journal;
    }

    private static function ledger(string $name): Ledger
    {
        return Ledger::where('name', $name)->firstOrFail();
    }
}
