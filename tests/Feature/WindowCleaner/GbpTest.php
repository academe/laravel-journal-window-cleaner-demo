<?php

use App\Demos\WindowCleaner\Support\Gbp;
use Money\Money;

it('formats GBP money with a pound sign', function () {
    expect(Gbp::format(Money::GBP(1500)))->toBe('£15.00')
        ->and(Gbp::format(Money::GBP(-300)))->toBe('-£3.00')
        ->and(Gbp::format(Money::GBP(5)))->toBe('£0.05')
        ->and(Gbp::format(Money::USD(500)))->toBe('USD 5.00');
});

it('parses decimal strings into GBP minor units', function () {
    expect(Gbp::parse('15.00')->getAmount())->toBe('1500')
        ->and(Gbp::parse('8.5')->getAmount())->toBe('850')
        ->and(Gbp::parse('0.01')->getAmount())->toBe('1');
});

it('splits VAT-inclusive prices without losing a penny', function (string $gross, string $net, string $vat) {
    $split = Gbp::vatSplit(Gbp::parse($gross));

    expect($split['net']->getAmount())->toBe($net)
        ->and($split['vat']->getAmount())->toBe($vat)
        ->and($split['net']->add($split['vat'])->equals(Gbp::parse($gross)))->toBeTrue();
})->with([
    '£15.00' => ['15.00', '1250', '250'],
    '£14.99 (awkward penny)' => ['14.99', '1250', '249'],
    '£8.50' => ['8.50', '709', '141'],
]);
