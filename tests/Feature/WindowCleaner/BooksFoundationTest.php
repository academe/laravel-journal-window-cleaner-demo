<?php

use Academe\LaravelJournal\Enums\StandardLedgerType;
use Academe\LaravelJournal\Models\Ledger;
use App\Demos\WindowCleaner\Actions\EnsureBooksExist;
use App\Demos\WindowCleaner\Models\CompanyAccount;
use App\Demos\WindowCleaner\Support\Books;

it('creates the five typed ledgers and four company account journals, idempotently', function () {
    app(EnsureBooksExist::class)->run();
    app(EnsureBooksExist::class)->run(); // second run must change nothing

    expect(Ledger::count())->toBe(5)
        ->and(CompanyAccount::count())->toBe(4)
        ->and(Books::debtorsLedger()->type)->toBe(StandardLedgerType::ASSET)
        ->and(Books::bankLedger()->type)->toBe(StandardLedgerType::ASSET)
        ->and(Books::salesLedger()->type)->toBe(StandardLedgerType::INCOME)
        ->and(Books::vatLedger()->type)->toBe(StandardLedgerType::LIABILITY)
        ->and(Books::expensesLedger()->type)->toBe(StandardLedgerType::EXPENSE)
        ->and(Books::salesJournal()->currency_code)->toBe('GBP')
        // NamesJournal on CompanyAccount feeds the package's display name.
        ->and(Books::salesJournal()->displayName())->toBe('Sales')
        ->and(Books::salesJournal()->ledger->name)->toBe(Books::LEDGER_SALES)
        ->and(Books::vatJournal()->ledger->name)->toBe(Books::LEDGER_VAT)
        ->and(Books::bankJournal()->ledger->name)->toBe(Books::LEDGER_BANK)
        ->and(Books::expensesJournal()->ledger->name)->toBe(Books::LEDGER_EXPENSES);
});
