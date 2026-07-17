<?php

namespace App\Demos\WindowCleaner\Actions;

use Academe\LaravelJournal\Exceptions\TransactionCouldNotBeProcessed;
use App\Demos\WindowCleaner\Models\ServicePlan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * "Do the round": charge every active plan whose next_due_on has
 * arrived, then roll the plan to its next occurrence.
 *
 * Each due plan is charged ONCE, posted at its scheduled next_due_on
 * (so a plan run late is still recorded on the day the schedule says
 * the visit happened), then rolled beyond $today. A plan overdue by
 * several intervals yields one visit, not several — the missed weeks
 * were simply not worked.
 *
 * The seeder replays history by calling this day by day with historical
 * dates; the admin button and demo:run-visits call it with today.
 *
 * Each plan's charge and schedule-advance commit atomically in one
 * transaction, so an interrupted run cannot double-charge on retry.
 *
 * A plan can be SKIPPED: it posts at its scheduled next_due_on, and if
 * that date falls on or before the latest checkpoint for a journal
 * involved (customer, sales, or VAT), the package refuses the post and
 * throws TransactionCouldNotBeProcessed — that's PeriodClosed doing its
 * job, not a bug. This demo surfaces that instead of crashing: the
 * plan is left untouched (next_due_on does NOT roll forward) and is
 * counted as skipped. It is not caught up automatically; a real system
 * would need the period reopened or the plan advanced manually, but
 * this is a demo, so the flashed message is left to show the package
 * behaviour rather than to fix it.
 */
class RunDueVisits
{
    public function __construct(protected ChargeVisit $chargeVisit) {}

    /**
     * @return array{charged: int, skipped: int}
     */
    public function run(?CarbonInterface $today = null): array
    {
        $today ??= today();

        $due = ServicePlan::query()
            ->where('active', true)
            ->whereDate('next_due_on', '<=', $today)
            ->with(['customer', 'service'])
            ->orderBy('next_due_on')
            ->get();

        $charged = 0;
        $skipped = 0;

        foreach ($due as $plan) {
            try {
                DB::transaction(function () use ($plan, $today): void {
                    $this->chargeVisit->run(
                        $plan->customer,
                        $plan->service,
                        $plan->priceAsMoney(),
                        $plan,
                        $plan->next_due_on,
                    );

                    $plan->rollForward($today);
                });

                $charged++;
            } catch (TransactionCouldNotBeProcessed) {
                $skipped++;
            }
        }

        return ['charged' => $charged, 'skipped' => $skipped];
    }
}
