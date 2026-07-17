<?php

namespace App\Demos\WindowCleaner\Support;

use Academe\LaravelJournal\Models\Journal;
use Academe\LaravelJournal\Models\Ledger;
use App\Demos\WindowCleaner\Models\CompanyAccount;

/**
 * Named lookups into the business's books (Level C).
 *
 * Four typed ledgers cover the accounting equation for this business:
 * Debtors + Bank (assets) = VAT owed (liability) + Sales (income).
 * Every customer journal lives in Debtors; the three company account
 * journals live in their own ledgers.
 */
final class Books
{
    public const SALES = 'Sales';

    public const VAT = 'VAT';

    public const BANK = 'Bank';

    public const LEDGER_DEBTORS = 'Debtors';

    public const LEDGER_BANK = 'Bank';

    public const LEDGER_SALES = 'Sales';

    public const LEDGER_VAT = 'VAT owed';

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

    private static function accountJournal(string $name): Journal
    {
        return CompanyAccount::where('name', $name)->firstOrFail()->journal;
    }

    private static function ledger(string $name): Ledger
    {
        return Ledger::where('name', $name)->firstOrFail();
    }
}
