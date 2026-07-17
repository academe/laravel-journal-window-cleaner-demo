<?php

use Academe\LaravelJournal\Models\JournalCheckpoint;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Purchase;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\SmsMessage;
use App\Demos\WindowCleaner\Models\Visit;
use App\Demos\WindowCleaner\Support\Books;
use App\Demos\WindowCleaner\Support\VatReturn;
use Database\Seeders\WindowCleanerSeeder;

it('seeds six months of balanced history', function () {
    $this->seed(WindowCleanerSeeder::class);

    expect(Service::count())->toBe(4)
        ->and(Customer::count())->toBe(10)
        ->and(Visit::count())->toBeGreaterThan(50)
        // Monthly supplies plus two equipment buys.
        ->and(Purchase::count())->toBeGreaterThanOrEqual(6)
        ->and(Purchase::where('category', 'equipment')->count())->toBe(2);

    // Level C: the books balance by construction.
    $assets = Books::debtorsLedger()->currentBalance('GBP')
        ->add(Books::bankLedger()->currentBalance('GBP'));
    $liabilitiesPlusIncomeLessExpenses = Books::vatLedger()->currentBalance('GBP')
        ->add(Books::salesLedger()->currentBalance('GBP'))
        ->subtract(Books::expensesLedger()->currentBalance('GBP'));
    expect($assets->equals($liabilitiesPlusIncomeLessExpenses))->toBeTrue();

    // Every seeded quarter's VAT return has both sides.
    $latest = VatReturn::for(VatReturn::quarters()[0]);
    expect($latest['outputVat']->isPositive())->toBeTrue()
        ->and($latest['inputVat']->isPositive())->toBeTrue();

    // Personas put balances on both sides of zero.
    $balances = Customer::all()->map(fn (Customer $c) => $c->balance());
    expect($balances->contains(fn ($b) => $b->isNegative()))->toBeTrue()
        ->and($balances->contains(fn ($b) => $b->isPositive()))->toBeTrue();

    // One historical month is closed, and the outbox has messages.
    expect(JournalCheckpoint::count())->toBeGreaterThan(0)
        ->and(SmsMessage::count())->toBeGreaterThan(0);
});

it('refuses to seed twice', function () {
    $this->seed(WindowCleanerSeeder::class);
    $this->seed(WindowCleanerSeeder::class); // must not throw or duplicate

    expect(Customer::count())->toBe(10);
});
