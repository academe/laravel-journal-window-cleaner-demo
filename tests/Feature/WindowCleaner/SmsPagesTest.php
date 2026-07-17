<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\SmsMessage;
use Money\Money;

it('sends balance texts from the admin button and shows them in the outbox', function () {
    $customer = Customer::factory()->create(['name' => 'Bill Sykes']);
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($customer, $service, Money::GBP(800));

    $this->post('/window-cleaner/admin/send-balance-texts')
        ->assertRedirect('/window-cleaner/admin/sms-outbox');

    expect(SmsMessage::count())->toBe(1);

    $this->get('/window-cleaner/admin/sms-outbox')
        ->assertOk()
        ->assertSee('Bill Sykes')
        ->assertSee('£8.00');
});
