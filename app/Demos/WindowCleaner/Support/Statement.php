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
 *
 * Each row also carries the VAT within it, read the books-first way:
 * an entry shares its transaction_group with the legs posted alongside
 * it, so the VAT inside a gross charge is its sibling leg on the VAT
 * journal — displayed as posted, never recomputed. Entries with no VAT
 * sibling (payments) get null. Not meaningful for the VAT journal's
 * own statement, where every entry would be its own sibling.
 */
final class Statement
{
    /**
     * @return Collection<int, array{transaction: JournalTransaction, vat: ?Money, running: Money}>
     */
    public static function for(Journal $journal): Collection
    {
        $transactions = $journal->transactions()
            ->orderBy('post_date')
            ->orderBy('created_at')
            ->get();

        // One query finds every sibling VAT leg for the whole statement.
        $vatLegsByGroup = JournalTransaction::query()
            ->whereIn('transaction_group', $transactions->pluck('transaction_group')->filter()->unique())
            ->where('journal_id', Books::vatJournal()->id)
            ->get()
            ->keyBy('transaction_group');

        $running = new Money(0, $journal->currency);

        return $transactions
            ->map(function (JournalTransaction $transaction) use (&$running, $vatLegsByGroup) {
                $running = $running->add($transaction->amount);

                $vatLeg = $transaction->transaction_group === null
                    ? null
                    : $vatLegsByGroup->get($transaction->transaction_group);

                return [
                    'transaction' => $transaction,
                    // Charges carry the VAT as a credit leg; a reversal
                    // would carry it as a debit. Either way, the magnitude.
                    'vat' => $vatLeg?->credit ?? $vatLeg?->debit,
                    'running' => $running,
                ];
            })
            ->reverse()
            ->values();
    }
}
