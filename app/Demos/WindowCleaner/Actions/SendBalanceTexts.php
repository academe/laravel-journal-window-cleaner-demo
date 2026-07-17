<?php

namespace App\Demos\WindowCleaner\Actions;

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Notifications\BalanceReminder;

/**
 * Text a balance reminder to every customer who owes money (Level A
 * read: one currentBalance() per customer decides who gets a text).
 */
class SendBalanceTexts
{
    public function run(): int
    {
        $sent = 0;

        foreach (Customer::query()->get() as $customer) {
            $owed = $customer->amountOwed();

            if ($owed->isZero()) {
                continue;
            }

            $customer->notify(new BalanceReminder($owed));
            $sent++;
        }

        return $sent;
    }
}
