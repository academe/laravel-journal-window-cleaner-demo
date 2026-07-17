<?php

use Academe\LaravelJournal\Exceptions\PeriodClosed;
use Academe\LaravelJournal\Exceptions\TransactionCouldNotBeProcessed;
use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Actions\CloseMonth;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use Illuminate\Support\Carbon;
use Money\Money;

beforeEach(function () {
    Carbon::setTestNow('2026-07-14 10:00:00');
    $this->customer = Customer::factory()->create();
    $this->service = Service::factory()->create();
});

it('checkpoints every journal through last month end', function () {
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500), null, Carbon::parse('2026-06-10'));

    $result = app(CloseMonth::class)->run();

    // 4 journals exist: the customer's plus Sales, VAT, Bank.
    expect($result['closed'])->toBe(4)
        ->and($result['skipped'])->toBe(0)
        ->and($result['date']->toDateString())->toBe('2026-06-30')
        ->and($this->customer->journal->fresh()->latestCheckpoint()->checkpoint_date->toDateString())->toBe('2026-06-30');

    // Balances still read correctly across the checkpoint boundary.
    expect($this->customer->journal->currentBalance()->equals(Money::GBP(-1500)))->toBeTrue();
});

it('is safe to run twice', function () {
    app(CloseMonth::class)->run();
    $second = app(CloseMonth::class)->run();

    expect($second['closed'])->toBe(0)
        ->and($second['skipped'])->toBe(4);
});

it('blocks back-dated postings into the closed period', function () {
    app(CloseMonth::class)->run(); // closes through 2026-06-30

    // TransactionGroup::commit() wraps the PeriodClosed in
    // TransactionCouldNotBeProcessed; the original is chained.
    try {
        app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500), null, Carbon::parse('2026-06-20'));
        $this->fail('Expected TransactionCouldNotBeProcessed');
    } catch (TransactionCouldNotBeProcessed $e) {
        expect($e->getPrevious())->toBeInstanceOf(PeriodClosed::class);
    }

    // Nothing was written: the whole group rolled back.
    expect($this->customer->journal->currentBalance()->isZero())->toBeTrue();
});
