<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Actions\EnsureBooksExist;
use App\Demos\WindowCleaner\Actions\RecordPurchase;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Support\Books;
use Money\Money;

beforeEach(function () {
    app(EnsureBooksExist::class)->run();
});

it('posts a purchase as one balanced three-leg group with the input VAT split out', function () {
    $purchase = app(RecordPurchase::class)->run('Squeaky Wholesale', 'supplies', Money::GBP(2394));

    $legs = $purchase->journalTransactions;
    expect($legs)->toHaveCount(3)
        ->and($legs->pluck('transaction_group')->unique())->toHaveCount(1)
        ->and($legs->first()->tags)->toBe(['kind' => 'purchase', 'category' => 'supplies'])
        ->and($legs->first()->getRawOriginal('reference_type'))->toBe('purchase');

    // Expenses is debit-normal: the net cost reads positive at ledger level.
    expect(Books::expensesLedger()->currentBalance('GBP')->equals(Money::GBP(1995)))->toBeTrue()
        // Bank fell by the gross amount actually paid.
        ->and(Books::bankLedger()->currentBalance('GBP')->equals(Money::GBP(-2394)))->toBeTrue()
        // VAT owed (credit-normal) goes negative: HMRC owes us the input VAT.
        ->and(Books::vatLedger()->currentBalance('GBP')->equals(Money::GBP(-399)))->toBeTrue();
});

it('nets input VAT against output VAT in the one VAT journal', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();

    app(ChargeVisit::class)->run($customer, $service, Money::GBP(1500));   // output VAT 250
    app(RecordPurchase::class)->run('Ladders R Us', 'equipment', Money::GBP(2394)); // input VAT 399

    // The journal's live balance is the net position: 250 collected − 399 reclaimable.
    expect(Books::vatJournal()->currentBalance()->equals(Money::GBP(-149)))->toBeTrue();
});

it('keeps the extended accounting equation balanced, even on awkward pennies', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();

    app(ChargeVisit::class)->run($customer, $service, Money::GBP(1499));
    app(RecordPurchase::class)->run('Squeaky Wholesale', 'supplies', Money::GBP(2394));

    $assets = Books::debtorsLedger()->currentBalance('GBP')
        ->add(Books::bankLedger()->currentBalance('GBP'));
    $liabilitiesPlusIncomeLessExpenses = Books::vatLedger()->currentBalance('GBP')
        ->add(Books::salesLedger()->currentBalance('GBP'))
        ->subtract(Books::expensesLedger()->currentBalance('GBP'));

    expect($assets->equals($liabilitiesPlusIncomeLessExpenses))->toBeTrue();
});

it('records the purchase with a historical post date when given one', function () {
    $date = now()->subMonths(2)->startOfDay();

    $purchase = app(RecordPurchase::class)->run('PureClean Systems', 'equipment', Money::GBP(24900), $date);

    expect($purchase->purchased_on->toDateString())->toBe($date->toDateString())
        ->and($purchase->journalTransactions->first()->post_date->toDateString())->toBe($date->toDateString())
        ->and(Books::expensesJournal()->balanceOn($date)->equals(Money::GBP(-20750)))->toBeTrue();
});
