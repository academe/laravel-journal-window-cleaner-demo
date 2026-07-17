<?php

use App\Demos\WindowCleaner\Models\Wallet;

it('serves the four tour pages and 404s unknown ones', function () {
    foreach (['level-a', 'level-b', 'level-c', 'checkpoints'] as $page) {
        $this->get("/window-cleaner/tour/{$page}")->assertOk();
    }

    $this->get('/window-cleaner/tour/level-z')->assertNotFound();
});

it('posts raw credits and debits on the playground wallet', function () {
    $this->post('/window-cleaner/tour/playground', [
        'direction' => 'credit', 'amount' => '10.00', 'currency' => 'GBP', 'memo' => 'top up',
    ])->assertRedirect('/window-cleaner/tour/playground');

    $this->post('/window-cleaner/tour/playground', [
        'direction' => 'debit', 'amount' => '2.50', 'currency' => 'GBP',
    ]);

    $wallet = Wallet::where('name', 'playground')->sole();
    expect($wallet->journal->currentBalance()->getAmount())->toBe('750');

    $this->get('/window-cleaner/tour/playground')
        ->assertOk()
        ->assertSee('£7.50')
        ->assertSee('top up');
});

it('demonstrates CurrencyMismatch when posting USD to the GBP wallet', function () {
    $this->post('/window-cleaner/tour/playground', [
        'direction' => 'credit', 'amount' => '10.00', 'currency' => 'GBP',
    ]);

    $this->post('/window-cleaner/tour/playground', [
        'direction' => 'credit', 'amount' => '5.00', 'currency' => 'USD',
    ])->assertRedirect('/window-cleaner/tour/playground')
        ->assertSessionHas('error');

    // Balance unchanged: the mismatched post never lands.
    $wallet = Wallet::where('name', 'playground')->sole();
    expect($wallet->journal->currentBalance()->getAmount())->toBe('1000');
});
