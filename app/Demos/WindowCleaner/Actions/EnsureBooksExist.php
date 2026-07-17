<?php

namespace App\Demos\WindowCleaner\Actions;

use Academe\LaravelJournal\Enums\StandardLedgerType;
use Academe\LaravelJournal\Models\Ledger;
use App\Demos\WindowCleaner\Models\CompanyAccount;
use App\Demos\WindowCleaner\Support\Books;

/**
 * Create the ledgers and company-account journals the business posts
 * into (Level C setup).
 *
 * Package concepts in play: a Ledger is a typed grouping (asset,
 * liability, equity, income, expense) used to roll up balances, while a
 * Journal is a single account's entry stream. assignToLedger() is what
 * ties a journal into the accounting equation via its ledger's type.
 *
 * Every journal must be owned by an Eloquent model, so the pure
 * accounting accounts (Sales, VAT, Bank, Expenses) each get a stand-in
 * CompanyAccount row to own their journal.
 *
 * initJournal('GBP') throws JournalAlreadyExists on a repeat call,
 * hence the journal()->doesntExist() guard — that guard is what makes
 * the whole action safe to re-run. Safe to run repeatedly: existing
 * rows are reused, so the seeder, tests, and factories can all call
 * it blindly.
 */
class EnsureBooksExist
{
    public function run(): void
    {
        Ledger::firstOrCreate(['name' => Books::LEDGER_DEBTORS], ['type' => StandardLedgerType::ASSET]);
        $bank = Ledger::firstOrCreate(['name' => Books::LEDGER_BANK], ['type' => StandardLedgerType::ASSET]);
        $sales = Ledger::firstOrCreate(['name' => Books::LEDGER_SALES], ['type' => StandardLedgerType::INCOME]);
        $vat = Ledger::firstOrCreate(['name' => Books::LEDGER_VAT], ['type' => StandardLedgerType::LIABILITY]);
        $expenses = Ledger::firstOrCreate(['name' => Books::LEDGER_EXPENSES], ['type' => StandardLedgerType::EXPENSE]);

        foreach ([[Books::SALES, $sales], [Books::VAT, $vat], [Books::BANK, $bank], [Books::EXPENSES, $expenses]] as [$name, $ledger]) {
            $account = CompanyAccount::firstOrCreate(['name' => $name]);

            if ($account->journal()->doesntExist()) {
                $account->initJournal('GBP')->assignToLedger($ledger);
            }
        }
    }
}
