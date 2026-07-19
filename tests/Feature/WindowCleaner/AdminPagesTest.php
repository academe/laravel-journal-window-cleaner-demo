<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\ServicePlan;
use Money\Money;

beforeEach(function () {
    $this->customer = Customer::factory()->create(['name' => 'Margaret Whitfield']);
    $this->service = Service::factory()->create(['name' => 'Full house']);
});

it('shows the dashboard with owed total, bank balance, and due visits', function () {
    ServicePlan::factory()->create([
        'customer_id' => $this->customer->id,
        'service_id' => $this->service->id,
        'next_due_on' => today()->subDay(),
    ]);
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500));

    $this->get('/window-cleaner/admin')
        ->assertOk()
        ->assertSee('Margaret Whitfield')
        ->assertSee('£15.00'); // total owed
});

it('lists customers with their balances', function () {
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500));

    $this->get('/window-cleaner/admin/customers')
        ->assertOk()
        ->assertSee('Margaret Whitfield')
        ->assertSee('£15.00');
});

it('shows a customer statement with running balance and tags', function () {
    app(ChargeVisit::class)->run($this->customer, $this->service, Money::GBP(1500));

    $this->get('/window-cleaner/admin/customers/'.$this->customer->id)
        ->assertOk()
        ->assertSee('Full house')
        ->assertSee('-£15.00')   // running balance after the charge
        ->assertSee('£2.50')     // "of which VAT": the group-sibling VAT leg
        ->assertSee('kind=visit');
});

it('records an ad-hoc visit from the admin form', function () {
    $this->post('/window-cleaner/admin/customers/'.$this->customer->id.'/visits', [
        'service_id' => $this->service->id,
        'price' => '12.50',
    ])->assertRedirect();

    expect($this->customer->journal->currentBalance()->equals(Money::GBP(-1250)))->toBeTrue();
});

it('records a manual payment from the admin form', function () {
    $this->post('/window-cleaner/admin/customers/'.$this->customer->id.'/payments', [
        'amount' => '10.00',
    ])->assertRedirect();

    expect($this->customer->journal->currentBalance()->equals(Money::GBP(1000)))->toBeTrue();
});

it('rejects a zero or negative payment', function () {
    $this->from('/window-cleaner/admin/customers/'.$this->customer->id)
        ->post('/window-cleaner/admin/customers/'.$this->customer->id.'/payments', ['amount' => '0'])
        ->assertSessionHasErrors('amount');
});
