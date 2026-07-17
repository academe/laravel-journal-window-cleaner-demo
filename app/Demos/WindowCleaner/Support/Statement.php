<?php

namespace App\Demos\WindowCleaner\Support;

use Academe\LaravelJournal\Models\Journal;
use Academe\LaravelJournal\Models\JournalTransaction;
use Illuminate\Support\Collection;
use Money\Money;

/**
 * A journal's entries with a running balance, newest first.
 *
 * JournalTransaction::$amount is the signed view of an entry (credit
 * positive, debit negative), so the running balance is a simple sum.
 */
final class Statement
{
    /**
     * @return Collection<int, array{transaction: JournalTransaction, running: Money}>
     */
    public static function for(Journal $journal): Collection
    {
        $running = new Money(0, $journal->currency);

        return $journal->transactions()
            ->orderBy('post_date')
            ->orderBy('created_at')
            ->get()
            ->map(function (JournalTransaction $transaction) use (&$running) {
                $running = $running->add($transaction->amount);

                return ['transaction' => $transaction, 'running' => $running];
            })
            ->reverse()
            ->values();
    }
}
