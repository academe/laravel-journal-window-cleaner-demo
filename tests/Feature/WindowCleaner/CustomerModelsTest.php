<?php

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Support\Books;
use Illuminate\Support\Carbon;

it('gives a factory customer a GBP journal in the Debtors ledger', function () {
    $customer = Customer::factory()->create();

    expect($customer->journal)->not->toBeNull()
        ->and($customer->journal->currency_code)->toBe('GBP')
        // The morph map (AppServiceProvider) stores the alias, not the FQCN.
        ->and($customer->journal->getRawOriginal('owner_type'))->toBe('customer')
        ->and($customer->journal->ledger->name)->toBe(Books::LEDGER_DEBTORS)
        ->and($customer->balance()->isZero())->toBeTrue()
        ->and($customer->amountOwed()->isZero())->toBeTrue();
});

it('rolls a plan forward past the given date, preserving the weekday', function () {
    $plan = ServicePlan::factory()->create([
        'interval_weeks' => 2,
        'next_due_on' => '2026-07-06', // a Monday
    ]);

    $plan->rollForward(Carbon::parse('2026-07-06'));
    expect($plan->fresh()->next_due_on->toDateString())->toBe('2026-07-20');

    $plan->rollForward(Carbon::parse('2026-08-25')); // long gap: must land beyond it
    expect($plan->fresh()->next_due_on->toDateString())->toBe('2026-08-31')
        ->and($plan->fresh()->next_due_on->isMonday())->toBeTrue();
});

it('knows when it is due', function () {
    $plan = ServicePlan::factory()->create(['next_due_on' => '2026-07-14', 'active' => true]);

    expect($plan->isDueOn(Carbon::parse('2026-07-14')))->toBeTrue()
        ->and($plan->isDueOn(Carbon::parse('2026-07-13')))->toBeFalse();

    $plan->update(['active' => false]);
    expect($plan->fresh()->isDueOn(Carbon::parse('2026-07-14')))->toBeFalse();
});
