<?php

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\Visit;
use App\Demos\WindowCleaner\Models\Wallet;

it('reaches journal entries from a referenced visit', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();

    $visit = Visit::create([
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'price' => 1500,
        'visited_on' => today(),
    ]);

    expect($visit->journalTransactions)->toHaveCount(0);

    $transaction = $customer->journal->debit(1500, 'test entry');
    $transaction->reference()->associate($visit)->save();

    expect($visit->fresh()->journalTransactions)->toHaveCount(1)
        ->and($visit->priceAsMoney()->getAmount())->toBe('1500')
        ->and($transaction->fresh()->getRawOriginal('reference_type'))->toBe('visit');
});

it('gives a wallet its own journal', function () {
    $wallet = Wallet::create(['name' => 'playground']);
    $wallet->initJournal('GBP');

    $wallet->journal->credit(500, 'first credit');

    expect($wallet->journal->currentBalance()->getAmount())->toBe('500')
        ->and($wallet->journal->getRawOriginal('owner_type'))->toBe('wallet');
});
