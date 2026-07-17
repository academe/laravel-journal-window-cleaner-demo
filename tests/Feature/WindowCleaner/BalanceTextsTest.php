<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Actions\SendBalanceTexts;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\SmsMessage;
use Money\Money;

it('texts exactly the customers who owe money', function () {
    $service = Service::factory()->create();

    $owing = Customer::factory()->create(['name' => 'Derek Hound', 'phone' => '07700 900123']);
    app(ChargeVisit::class)->run($owing, $service, Money::GBP(2350));

    $inCredit = Customer::factory()->create();

    $sent = app(SendBalanceTexts::class)->run();

    expect($sent)->toBe(1)
        ->and(SmsMessage::count())->toBe(1);

    $message = SmsMessage::sole();
    expect($message->customer_id)->toBe($owing->id)
        ->and($message->phone)->toBe('07700 900123')
        ->and($message->body)->toContain('Derek Hound')
        ->and($message->body)->toContain('£23.50')
        ->and($message->sent_at)->not->toBeNull()
        ->and(SmsMessage::where('customer_id', $inCredit->id)->count())->toBe(0);
});
