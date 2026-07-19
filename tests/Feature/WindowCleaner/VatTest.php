<?php

use Academe\LaravelJournal\Support\MoneyFormatter;
use App\Demos\WindowCleaner\Support\Books;
use App\Demos\WindowCleaner\Support\Vat;

it('builds money in the configured demo currency', function () {
    // Default config; US-based developers can set DEMO_CURRENCY=USD.
    expect(Books::currencyCode())->toBe('GBP')
        ->and(Books::money(1500)->getCurrency()->getCode())->toBe('GBP')
        ->and(Books::money(1500)->getAmount())->toBe('1500');
});

it('splits VAT-inclusive prices without losing a penny', function (string $gross, string $net, string $vat) {
    $split = Vat::split(MoneyFormatter::parseDecimal($gross, Books::currency()));

    expect($split['net']->getAmount())->toBe($net)
        ->and($split['vat']->getAmount())->toBe($vat)
        ->and($split['net']->add($split['vat'])->equals(MoneyFormatter::parseDecimal($gross, Books::currency())))->toBeTrue();
})->with([
    '£15.00' => ['15.00', '1250', '250'],
    '£14.99 (awkward penny)' => ['14.99', '1250', '249'],
    '£8.50' => ['8.50', '709', '141'],
]);
