<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Actions\CloseMonth;
use App\Demos\WindowCleaner\Actions\RecordPurchase;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Support\VatReturn;
use Illuminate\Support\Carbon;
use Money\Money;

beforeEach(function () {
    $this->customer = Customer::factory()->create();
    $this->service = Service::factory()->create();

    // Q1: two sales (one on the quarter's last day) and one purchase.
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500), null, Carbon::parse('2026-02-10'));
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1499), null, Carbon::parse('2026-03-31'));
    app(RecordPurchase::class)->run('Squeaky Wholesale', 'supplies', Money::GBP(2394), Carbon::parse('2026-02-03'));

    // Q2: one sale on the quarter's first day and one purchase.
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500), null, Carbon::parse('2026-04-01'));
    app(RecordPurchase::class)->run('Ladders R Us', 'equipment', Money::GBP(18000), Carbon::parse('2026-05-15'));
});

it('lists the populated calendar quarters, newest first', function () {
    expect(VatReturn::quarters())->toBe(['2026-Q2', '2026-Q1']);
});

it('computes a quarter from the VAT journal, both sides netted', function () {
    $q1 = VatReturn::for('2026-Q1');

    expect($q1['sales'])->toHaveCount(2)
        ->and($q1['purchases'])->toHaveCount(1)
        // Output VAT: 250 + 249; input VAT: 399; net due = 499 - 399.
        ->and($q1['outputVat']->equals(Money::GBP(499)))->toBeTrue()
        ->and($q1['inputVat']->equals(Money::GBP(399)))->toBeTrue()
        ->and($q1['netDue']->equals(Money::GBP(100)))->toBeTrue()
        ->and($q1['netSales']->equals(Money::GBP(2500)))->toBeTrue()
        ->and($q1['netPurchases']->equals(Money::GBP(1995)))->toBeTrue();
});

it('puts quarter-boundary entries in the right quarter', function () {
    $q2 = VatReturn::for('2026-Q2');

    // Only the 1 April sale and the May purchase: the 31 March sale stays in Q1.
    expect($q2['sales'])->toHaveCount(1)
        ->and($q2['purchases'])->toHaveCount(1)
        ->and($q2['outputVat']->equals(Money::GBP(250)))->toBeTrue()
        ->and($q2['inputVat']->equals(Money::GBP(3000)))->toBeTrue()
        ->and($q2['netDue']->equals(Money::GBP(-2750)))->toBeTrue();
});

it('reports whether the quarter falls inside the closed period', function () {
    // Checkpoint every journal through 31 March (the month before 15 April).
    app(CloseMonth::class)->run(Carbon::parse('2026-04-15'));

    expect(VatReturn::for('2026-Q1')['closed'])->toBeTrue()
        ->and(VatReturn::for('2026-Q2')['closed'])->toBeFalse();
});
