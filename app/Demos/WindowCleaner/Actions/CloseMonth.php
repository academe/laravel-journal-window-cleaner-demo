<?php

namespace App\Demos\WindowCleaner\Actions;

use Academe\LaravelJournal\Models\Journal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Close the previous month: checkpoint every journal through its last
 * day. A checkpoint freezes the period behind it (posting, editing, or
 * deleting entries dated on or before it throws PeriodClosed) and
 * stores cumulative totals so balance queries scan only newer entries.
 *
 * Journals already checkpointed at or beyond the target date are
 * skipped, so clicking "Close month" twice is harmless.
 */
class CloseMonth
{
    /**
     * @param  CarbonInterface|null  $asOf  Defaults to now(). Determines
     *                                      which month gets closed — the
     *                                      month BEFORE $asOf, not the
     *                                      month it falls in.
     * @return array{date: CarbonInterface, closed: int, skipped: int}
     */
    public function run(?CarbonInterface $asOf = null): array
    {
        $date = Carbon::instance($asOf ?? now())
            ->subMonthNoOverflow()
            ->endOfMonth()
            ->startOfDay();

        $closed = 0;
        $skipped = 0;

        foreach (Journal::query()->get() as $journal) {
            $latest = $journal->latestCheckpoint();

            if ($latest !== null && $latest->checkpoint_date->gte($date)) {
                $skipped++;

                continue;
            }

            $journal->checkpoint($date);
            $closed++;
        }

        return ['date' => $date, 'closed' => $closed, 'skipped' => $skipped];
    }
}
