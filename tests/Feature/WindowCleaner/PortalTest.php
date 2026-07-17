<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Payment;
use App\Demos\WindowCleaner\Models\Service;
use Money\Money;

beforeEach(function () {
    $this->customer = Customer::factory()->create(['name' => 'Margaret Whitfield']);
});

it('asks you to pick a customer when none is selected', function () {
    $this->get('/window-cleaner/portal/account')
        ->assertRedirect('/window-cleaner/portal');

    $this->get('/window-cleaner/portal')
        ->assertOk()
        ->assertSee('Margaret Whitfield');
});

it('acts as the chosen customer', function () {
    $this->post('/window-cleaner/portal/act-as/'.$this->customer->id)
        ->assertRedirect('/window-cleaner/portal/account');

    $this->get('/window-cleaner/portal/account')
        ->assertOk()
        ->assertSee('Margaret Whitfield');
});

it('shows the balance owed on the account page', function () {
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($this->customer, $service, Money::GBP(2300));

    $this->post('/window-cleaner/portal/act-as/'.$this->customer->id);

    $this->get('/window-cleaner/portal/account')
        ->assertOk()
        ->assertSee('You owe')
        ->assertSee('£23.00');
});

it('takes an online payment, prefilled but editable (overpay)', function () {
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($this->customer, $service, Money::GBP(1500));
    $this->post('/window-cleaner/portal/act-as/'.$this->customer->id);

    $this->get('/window-cleaner/portal/pay')
        ->assertOk()
        ->assertSee('value="15.00"', false); // prefilled with amount owed

    $this->post('/window-cleaner/portal/pay', ['amount' => '20.00'])
        ->assertRedirect(); // to wc.portal.paid for the new payment

    expect($this->customer->journal->currentBalance()->equals(Money::GBP(500)))->toBeTrue();

    $payment = Payment::latest('id')->first();
    $this->get('/window-cleaner/portal/paid/'.$payment->id)
        ->assertOk()
        ->assertSee('£20.00')
        ->assertSee('in credit');
});

it('redirects the paid page to the switcher when no customer is selected', function () {
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($this->customer, $service, Money::GBP(1500));
    $this->post('/window-cleaner/portal/act-as/'.$this->customer->id);
    $this->post('/window-cleaner/portal/pay', ['amount' => '15.00']);

    $payment = Payment::latest('id')->first();

    $this->flushSession();

    $this->get('/window-cleaner/portal/paid/'.$payment->id)
        ->assertRedirect('/window-cleaner/portal');
});

it('refuses to show another customer\'s payment receipt', function () {
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($this->customer, $service, Money::GBP(1500));
    $this->post('/window-cleaner/portal/act-as/'.$this->customer->id);
    $this->post('/window-cleaner/portal/pay', ['amount' => '15.00']);

    $payment = Payment::latest('id')->first();

    $other = Customer::factory()->create(['name' => 'Arthur Pemberton']);
    $this->post('/window-cleaner/portal/act-as/'.$other->id);

    $this->get('/window-cleaner/portal/paid/'.$payment->id)
        ->assertNotFound();
});

it('rejects invalid amounts', function () {
    $this->post('/window-cleaner/portal/act-as/'.$this->customer->id);

    $this->from('/window-cleaner/portal/pay')
        ->post('/window-cleaner/portal/pay', ['amount' => '0'])
        ->assertSessionHasErrors('amount');

    $this->from('/window-cleaner/portal/pay')
        ->post('/window-cleaner/portal/pay', ['amount' => '-5'])
        ->assertSessionHasErrors('amount');

    $this->from('/window-cleaner/portal/pay')
        ->post('/window-cleaner/portal/pay', ['amount' => '1001'])
        ->assertSessionHasErrors('amount');
});
