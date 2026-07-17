<?php

use Academe\LaravelJournal\Enums\StandardLedgerType;
use Academe\LaravelJournal\Models\Ledger;
use App\Demos\WindowCleaner\Actions\EnsureBooksExist;
use App\Demos\WindowCleaner\Models\CompanyAccount;
use App\Demos\WindowCleaner\Support\Books;

it('creates the four typed ledgers and three company account journals, idempotently', function () {
    app(EnsureBooksExist::class)->run();
    app(EnsureBooksExist::class)->run(); // second run must change nothing

    expect(Ledger::count())->toBe(4)
        ->and(CompanyAccount::count())->toBe(3)
        ->and(Books::debtorsLedger()->type)->toBe(StandardLedgerType::ASSET)
        ->and(Books::bankLedger()->type)->toBe(StandardLedgerType::ASSET)
        ->and(Books::salesLedger()->type)->toBe(StandardLedgerType::INCOME)
        ->and(Books::vatLedger()->type)->toBe(StandardLedgerType::LIABILITY)
        ->and(Books::salesJournal()->currency_code)->toBe('GBP')
        ->and(Books::salesJournal()->ledger->name)->toBe(Books::LEDGER_SALES)
        ->and(Books::vatJournal()->ledger->name)->toBe(Books::LEDGER_VAT)
        ->and(Books::bankJournal()->ledger->name)->toBe(Books::LEDGER_BANK);
});
