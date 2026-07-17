<?php

use Academe\LaravelJournal\Models\JournalCheckpoint;
use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Models\Visit;
use Money\Money;

it('runs due visits from the dashboard button', function () {
    ServicePlan::factory()->create(['next_due_on' => today()->subDay()]);

    $this->post('/window-cleaner/admin/run-visits')
        ->assertRedirect('/window-cleaner/admin');

    expect(Visit::count())->toBe(1);
});

it('shows the books with ledger balances and a balanced equation', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();
    app(ChargeVisit::class)->run($customer, $service, Money::GBP(1500));

    $this->get('/window-cleaner/admin/books')
        ->assertOk()
        ->assertSee('Debtors')
        ->assertSee('VAT owed')
        ->assertSee('£12.50')  // Sales
        ->assertSee('£2.50')   // VAT
        ->assertSee('balances'); // the equation verdict line
});

it('closes the month and reports it', function () {
    Customer::factory()->create(); // ensures books + one customer journal exist

    $this->post('/window-cleaner/admin/close-month')
        ->assertRedirect('/window-cleaner/admin/close-month');

    expect(JournalCheckpoint::count())->toBe(4);

    $this->get('/window-cleaner/admin/close-month')
        ->assertOk()
        ->assertSee(now()->subMonthNoOverflow()->endOfMonth()->toFormattedDateString());
});
