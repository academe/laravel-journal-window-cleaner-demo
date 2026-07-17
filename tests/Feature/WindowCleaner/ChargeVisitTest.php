<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Support\Books;
use Money\Money;

beforeEach(function () {
    $this->customer = Customer::factory()->create();
    $this->service = Service::factory()->create(['name' => 'Full house']);
});

it('charges a visit as one balanced three-leg group with the VAT split out', function () {
    $visit = app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500));

    // Level A: the customer's running balance shows the debt.
    expect($this->customer->journal->currentBalance()->equals(Money::GBP(-1500)))->toBeTrue();

    // Level B: three legs, one shared group UUID, reachable from the visit.
    $legs = $visit->journalTransactions;
    expect($legs)->toHaveCount(3)
        ->and($legs->pluck('transaction_group')->unique())->toHaveCount(1)
        ->and($legs->first()->tags)->toBe(['kind' => 'visit', 'service' => 'full-house']);

    // The business side: net to Sales, VAT to VAT owed.
    expect(Books::salesJournal()->currentBalance()->equals(Money::GBP(1250)))->toBeTrue()
        ->and(Books::vatJournal()->currentBalance()->equals(Money::GBP(250)))->toBeTrue();
});

it('keeps the accounting equation balanced, even on awkward pennies', function () {
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1499));

    $assets = Books::debtorsLedger()->currentBalance('GBP')
        ->add(Books::bankLedger()->currentBalance('GBP'));
    $liabilitiesPlusIncome = Books::vatLedger()->currentBalance('GBP')
        ->add(Books::salesLedger()->currentBalance('GBP'));

    expect($assets->equals($liabilitiesPlusIncome))->toBeTrue()
        ->and(Books::debtorsLedger()->currentBalance('GBP')->equals(Money::GBP(1499)))->toBeTrue()
        ->and(Books::salesLedger()->currentBalance('GBP')->equals(Money::GBP(1250)))->toBeTrue()
        ->and(Books::vatLedger()->currentBalance('GBP')->equals(Money::GBP(249)))->toBeTrue();
});

it('records the visit with a historical post date when given one', function () {
    $date = now()->subMonths(2)->startOfDay();

    $visit = app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500), null, $date);

    expect($visit->visited_on->toDateString())->toBe($date->toDateString())
        ->and($visit->journalTransactions->first()->post_date->toDateString())->toBe($date->toDateString())
        ->and($this->customer->journal->balanceOn($date)->equals(Money::GBP(-1500)))->toBeTrue();
});
