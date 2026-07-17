<?php

namespace App\Demos\WindowCleaner\Actions;

use Academe\LaravelJournal\TransactionGroup;
use App\Demos\WindowCleaner\Models\Purchase;
use App\Demos\WindowCleaner\Support\Books;
use App\Demos\WindowCleaner\Support\Gbp;
use App\Demos\WindowCleaner\Support\TagsTransactionGroups;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Money\Money;

/**
 * Record a purchase of supplies or equipment, paid immediately from the
 * bank — the input-VAT mirror of ChargeVisit.
 *
 * The VAT-inclusive gross is split into net + VAT (VAT computed from the
 * gross and rounded down, net takes the remainder), then posted as ONE
 * balanced TransactionGroup:
 *
 *   debit  Expenses journal   £19.95   (cost of running the round)
 *   debit  VAT journal         £3.99   (input VAT, reclaimable)
 *   credit Bank journal       £23.94   (what actually left the bank)
 *
 * Debiting the same VAT journal that sales credit means its running
 * balance is always the NET VAT position: output collected minus input
 * reclaimable. The quarterly VAT return reads the two sides back apart
 * as credit legs (sales) and debit legs (purchases).
 *
 * Every leg references the Purchase row and is tagged for filtering.
 * The whole run is wrapped in an outer DB::transaction so the Purchase
 * row and its journal legs are atomic — a failed commit() leaves no
 * orphaned Purchase behind.
 */
class RecordPurchase
{
    use TagsTransactionGroups;

    public function run(
        string $supplier,
        string $category,
        Money $gross,
        ?CarbonInterface $date = null,
    ): Purchase {
        return DB::transaction(function () use ($supplier, $category, $gross, $date) {
            $date ??= now();

            ['net' => $net, 'vat' => $vat] = Gbp::vatSplit($gross);

            $purchase = Purchase::create([
                'supplier' => $supplier,
                'category' => $category,
                'price' => (int) $gross->getAmount(),
                'purchased_on' => $date->toDateString(),
            ]);

            $memo = ucfirst($category)." from {$supplier} on {$date->toDateString()}";

            $groupUuid = TransactionGroup::make()
                ->addTransaction(Books::expensesJournal(), 'debit', $net, $memo, $purchase, $date)
                ->addTransaction(Books::vatJournal(), 'debit', $vat, "VAT on {$memo}", $purchase, $date)
                ->addTransaction(Books::bankJournal(), 'credit', $gross, $memo, $purchase, $date)
                ->commit();

            $this->tagGroup($groupUuid, [
                'kind' => 'purchase',
                'category' => $category,
            ]);

            return $purchase;
        });
    }
}
