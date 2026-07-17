<?php

namespace App\Demos\WindowCleaner\Actions;

use Academe\LaravelJournal\TransactionGroup;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Payment;
use App\Demos\WindowCleaner\Support\Books;
use App\Demos\WindowCleaner\Support\TagsTransactionGroups;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Money\Money;

/**
 * Record a customer payment: credit their journal, debit the Bank
 * journal — one balanced TransactionGroup (Level B).
 *
 * Over- and underpayment need no special handling anywhere in the
 * demo: the balance simply moves, through zero if that's where it
 * goes. Both legs reference the Payment row.
 *
 * The whole run is wrapped in an outer DB::transaction so the Payment
 * row and its journal legs are atomic too — if commit() throws, no
 * orphaned Payment is left behind.
 */
class RecordPayment
{
    use TagsTransactionGroups;

    public function run(
        Customer $customer,
        Money $amount,
        ?CarbonInterface $date = null,
        string $method = 'online',
    ): Payment {
        return DB::transaction(function () use ($customer, $amount, $date, $method) {
            $date ??= now();

            $payment = Payment::create([
                'customer_id' => $customer->id,
                'amount' => (int) $amount->getAmount(),
                'method' => $method,
                'paid_at' => $date,
            ]);

            $memo = "Payment received ({$method})";

            $groupUuid = TransactionGroup::make()
                ->addTransaction($customer->journal, 'credit', $amount, $memo, $payment, $date)
                ->addTransaction(Books::bankJournal(), 'debit', $amount, $memo, $payment, $date)
                ->commit();

            $this->tagGroup($groupUuid, [
                'kind' => 'payment',
                'channel' => $method,
            ]);

            return $payment;
        });
    }
}
