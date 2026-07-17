<?php

namespace App\Demos\WindowCleaner\Support;

use Academe\LaravelJournal\Models\Journal;

/**
 * A journal has no name of its own — its identity is its owner model.
 * Every owner in this demo (Customer, CompanyAccount, Wallet) has a
 * name attribute, so display resolves through the owner morph.
 */
final class JournalName
{
    public static function of(Journal $journal): string
    {
        $owner = $journal->owner;

        return $owner->name ?? class_basename($owner).' #'.$owner->getKey();
    }
}
