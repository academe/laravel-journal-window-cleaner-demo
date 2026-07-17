<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Actions\RecordPayment;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Support\Books;
use Money\Money;

beforeEach(function () {
    $this->customer = Customer::factory()->create();
});

it('credits the customer and debits the bank in one group', function () {
    $payment = app(RecordPayment::class)->run($this->customer, Money::GBP(1000));

    expect($this->customer->journal->currentBalance()->equals(Money::GBP(1000)))->toBeTrue()
        ->and(Books::bankLedger()->currentBalance('GBP')->equals(Money::GBP(1000)))->toBeTrue()
        ->and($payment->journalTransactions)->toHaveCount(2)
        ->and($payment->journalTransactions->first()->tags)
        ->toBe(['kind' => 'payment', 'channel' => 'online'])
        ->and($payment->method)->toBe('online');
});

it('lets a customer overpay straight through zero', function () {
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($this->customer, $service, Money::GBP(1500));

    app(RecordPayment::class)->run($this->customer, Money::GBP(2000));

    // Owed £15, paid £20: now £5 in credit. No special handling anywhere.
    expect($this->customer->journal->currentBalance()->equals(Money::GBP(500)))->toBeTrue()
        ->and($this->customer->amountOwed()->isZero())->toBeTrue();
});

it('records underpayment just as happily', function () {
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($this->customer, $service, Money::GBP(1500));

    app(RecordPayment::class)->run($this->customer, Money::GBP(500), null, 'manual');

    expect($this->customer->journal->currentBalance()->equals(Money::GBP(-1000)))->toBeTrue()
        ->and($this->customer->amountOwed()->equals(Money::GBP(1000)))->toBeTrue();
});
