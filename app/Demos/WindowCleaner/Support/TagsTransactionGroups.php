<?php

namespace App\Demos\WindowCleaner\Support;

use Academe\LaravelJournal\Models\JournalTransaction;

/**
 * Tag every entry in a committed transaction group.
 *
 * TransactionGroup::commit() returns the shared group UUID; tags are a
 * per-entry attribute, so they're applied to the entries afterwards.
 */
trait TagsTransactionGroups
{
    /**
     * @param  array<string, bool|int|float|string>  $tags
     */
    protected function tagGroup(string $groupUuid, array $tags): void
    {
        JournalTransaction::where('transaction_group', $groupUuid)
            ->get()
            ->each(function (JournalTransaction $transaction) use ($tags) {
                $transaction->tags = $tags;
                $transaction->save();
            });
    }
}
