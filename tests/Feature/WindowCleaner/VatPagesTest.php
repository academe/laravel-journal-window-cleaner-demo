<?php

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Actions\EnsureBooksExist;
use App\Demos\WindowCleaner\Actions\RecordPurchase;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Purchase;
use App\Demos\WindowCleaner\Models\Service;
use Illuminate\Support\Carbon;
use Money\Money;

it('records a purchase through the form and lists it', function () {
    app(EnsureBooksExist::class)->run();

    $this->post('/window-cleaner/admin/purchases', [
        'supplier' => 'Squeaky Wholesale',
        'category' => 'supplies',
        'price' => '23.94',
    ])->assertRedirect('/window-cleaner/admin/purchases');

    $purchase = Purchase::sole();
    expect($purchase->price)->toBe(2394)
        ->and($purchase->journalTransactions)->toHaveCount(3);

    $this->get('/window-cleaner/admin/purchases')
        ->assertOk()
        ->assertSee('Squeaky Wholesale')
        ->assertSee('£23.94');
});

it('rejects a bad category and a zero price', function () {
    app(EnsureBooksExist::class)->run();

    $this->post('/window-cleaner/admin/purchases', [
        'supplier' => 'Squeaky Wholesale',
        'category' => 'snacks',
        'price' => '0',
    ])->assertSessionHasErrors(['category', 'price']);

    expect(Purchase::count())->toBe(0);
});

it('shows the VAT return for a chosen quarter with totals and details', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create(['name' => 'Full house']);

    app(ChargeVisit::class)->run($customer, $service, Money::GBP(1500), null, Carbon::parse('2026-02-10'));
    app(RecordPurchase::class)->run('Squeaky Wholesale', 'supplies', Money::GBP(2394), Carbon::parse('2026-02-03'));
    app(ChargeVisit::class)->run($customer, $service, Money::GBP(1500), null, Carbon::parse('2026-04-20'));

    // Q1 by explicit choice: output 250, input 399, net due -149 (reclaimable).
    $this->get('/window-cleaner/admin/vat-return?quarter=2026-Q1')
        ->assertOk()
        ->assertSee('2026-Q1')
        ->assertSee('2026-Q2')
        ->assertSee('£2.50')
        ->assertSee('£3.99')
        ->assertSee('-£1.49')
        ->assertSee('Reclaimable')
        ->assertSee('Squeaky Wholesale')
        ->assertSee($customer->name);

    // Default (no query) is the newest quarter: Q2, sales only.
    $this->get('/window-cleaner/admin/vat-return')
        ->assertOk()
        ->assertSee('No purchases this quarter.');

    // An unpopulated quarter 404s rather than rendering nonsense.
    $this->get('/window-cleaner/admin/vat-return?quarter=2031-Q1')->assertNotFound();
});

it('shows an empty state when there is no VAT activity at all', function () {
    app(EnsureBooksExist::class)->run();

    $this->get('/window-cleaner/admin/vat-return')
        ->assertOk()
        ->assertSee('No VAT activity yet');
});
