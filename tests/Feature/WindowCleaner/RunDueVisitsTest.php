<?php

use App\Demos\WindowCleaner\Actions\CloseMonth;
use App\Demos\WindowCleaner\Actions\RunDueVisits;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Models\Visit;
use Illuminate\Support\Carbon;

it('charges exactly the due plans and rolls their dates beyond today', function () {
    $today = Carbon::parse('2026-07-14'); // a Tuesday

    $dueToday = ServicePlan::factory()->create(['next_due_on' => '2026-07-14', 'interval_weeks' => 2, 'price' => 1500]);
    $overdue = ServicePlan::factory()->create(['next_due_on' => '2026-07-10', 'interval_weeks' => 1, 'price' => 850]);
    $notDue = ServicePlan::factory()->create(['next_due_on' => '2026-07-15', 'interval_weeks' => 2]);
    $inactive = ServicePlan::factory()->create(['next_due_on' => '2026-07-14', 'active' => false]);

    $result = app(RunDueVisits::class)->run($today);

    expect($result)->toBe(['charged' => 2, 'skipped' => 0])
        ->and(Visit::count())->toBe(2);

    // The overdue plan is charged at its scheduled date, not today.
    $overdueVisit = Visit::where('service_plan_id', $overdue->id)->sole();
    expect($overdueVisit->visited_on->toDateString())->toBe('2026-07-10');

    // Both charged plans roll beyond today; the others are untouched.
    expect($dueToday->fresh()->next_due_on->toDateString())->toBe('2026-07-28')
        ->and($overdue->fresh()->next_due_on->toDateString())->toBe('2026-07-17')
        ->and($notDue->fresh()->next_due_on->toDateString())->toBe('2026-07-15')
        ->and($inactive->fresh()->next_due_on->toDateString())->toBe('2026-07-14');

    // Charged customers now owe their plan price.
    expect($dueToday->customer->journal->currentBalance()->getAmount())->toBe('-1500');
});

it('is exposed as an artisan command', function () {
    ServicePlan::factory()->create(['next_due_on' => today()->subDay()]);

    $this->artisan('demo:run-visits')
        ->expectsOutputToContain('Charged 1 due visit(s), skipped 0 plan(s) in a closed period.')
        ->assertSuccessful();
});

it('skips a plan whose due date falls in a closed period instead of crashing', function () {
    Carbon::setTestNow('2026-07-14 10:00:00');

    // Due back in June, before the checkpoint we're about to create.
    $stale = ServicePlan::factory()->create(['next_due_on' => '2026-06-10', 'interval_weeks' => 4, 'price' => 1500]);

    // Close through 2026-06-30, checkpointing all journals.
    app(CloseMonth::class)->run();

    // A normal, current-dated plan due in the same run.
    $current = ServicePlan::factory()->create(['next_due_on' => '2026-07-14', 'interval_weeks' => 2, 'price' => 850]);

    $result = app(RunDueVisits::class)->run(Carbon::parse('2026-07-14'));

    expect($result)->toBe(['charged' => 1, 'skipped' => 1])
        ->and(Visit::count())->toBe(1);

    // The skipped plan is untouched: no visit, next_due_on unchanged.
    expect(Visit::where('service_plan_id', $stale->id)->exists())->toBeFalse()
        ->and($stale->fresh()->next_due_on->toDateString())->toBe('2026-06-10');

    // The current-dated plan in the same run was still charged normally.
    expect(Visit::where('service_plan_id', $current->id)->exists())->toBeTrue()
        ->and($current->fresh()->next_due_on->toDateString())->toBe('2026-07-28');
});
