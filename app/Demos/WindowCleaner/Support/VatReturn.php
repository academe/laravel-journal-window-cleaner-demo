<?php

namespace App\Demos\WindowCleaner\Support;

use Academe\LaravelJournal\Models\JournalTransaction;
use App\Demos\WindowCleaner\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Money\Money;

/**
 * The quarterly VAT return, read straight off the VAT journal.
 *
 * Sales credit the VAT journal (output VAT) and purchases debit it
 * (input VAT), so one date-ranged query over its transactions IS the
 * return: credit legs are the sales side, debit legs the purchases
 * side, and the journal needs no help remembering which was which —
 * the package's debit/credit Money casts read the two columns apart.
 *
 * Each VAT leg's sibling legs (same transaction_group) supply the net
 * amount — the Sales leg for a sale, the Expenses leg for a purchase —
 * and the reference morph walks back to the Visit or Purchase. Nothing
 * is stored: the report cannot drift from the books because it is the
 * books.
 */
final class VatReturn
{
    /**
     * Every calendar quarter between the first and last VAT journal
     * entry, newest first, labelled like "2026-Q1".
     *
     * @return list<string>
     */
    public static function quarters(): array
    {
        $transactions = Books::vatJournal()->transactions();

        $first = $transactions->min('post_date');
        $last = $transactions->max('post_date');

        if ($first === null) {
            return [];
        }

        $quarters = [];
        $cursor = Carbon::parse($first)->firstOfQuarter();
        $end = Carbon::parse($last);

        while ($cursor->lte($end)) {
            $quarters[] = $cursor->year.'-Q'.$cursor->quarter;
            $cursor = $cursor->addMonths(3);
        }

        return array_reverse($quarters);
    }

    /**
     * Compute one quarter's return from the VAT journal.
     *
     * @return array{
     *     quarter: string, start: Carbon, end: Carbon, closed: bool,
     *     sales: Collection<int, array{date: Carbon, memo: string, reference: mixed, net: Money, vat: Money}>,
     *     purchases: Collection<int, array{date: Carbon, memo: string, reference: mixed, net: Money, vat: Money}>,
     *     outputVat: Money, inputVat: Money, netDue: Money,
     *     netSales: Money, netPurchases: Money,
     * }
     */
    public static function for(string $quarter): array
    {
        [$start, $end] = self::range($quarter);

        $journal = Books::vatJournal();

        $vatLegs = $journal->transactions()
            ->whereBetween('post_date', [$start, $end])
            ->orderBy('post_date')
            ->orderBy('created_at')
            ->with('reference')
            ->get();

        // The sales table shows the customer, reached through the Visit
        // reference; preload it so the view doesn't query per row.
        $vatLegs->loadMorph('reference', [Visit::class => ['customer']]);

        // One query fetches every sibling leg of every group in the
        // period; the net amount lives on the Sales or Expenses leg.
        $siblingsByGroup = JournalTransaction::query()
            ->whereIn('transaction_group', $vatLegs->pluck('transaction_group')->filter()->unique())
            ->get()
            ->groupBy('transaction_group');

        $salesJournalId = Books::salesJournal()->id;
        $expensesJournalId = Books::expensesJournal()->id;

        $sales = collect();
        $purchases = collect();

        foreach ($vatLegs as $leg) {
            $siblings = $siblingsByGroup->get($leg->transaction_group, collect());

            if ($leg->credit !== null) {
                $net = $siblings->firstWhere('journal_id', $salesJournalId);
                $sales->push(self::row($leg, $leg->credit, $net?->credit));
            } else {
                $net = $siblings->firstWhere('journal_id', $expensesJournalId);
                $purchases->push(self::row($leg, $leg->debit, $net?->debit));
            }
        }

        $outputVat = self::total($sales, 'vat');
        $inputVat = self::total($purchases, 'vat');

        return [
            'quarter' => $quarter,
            'start' => $start,
            'end' => $end,
            // Read-only note: a checkpoint at or beyond the quarter's
            // end means the period is closed to further posting.
            'closed' => $journal->latestCheckpoint()?->checkpoint_date
                ->gte($end->copy()->startOfDay()) ?? false,
            'sales' => $sales,
            'purchases' => $purchases,
            'outputVat' => $outputVat,
            'inputVat' => $inputVat,
            'netDue' => $outputVat->subtract($inputVat),
            'netSales' => self::total($sales, 'net'),
            'netPurchases' => self::total($purchases, 'net'),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon} start and end of the quarter
     */
    private static function range(string $quarter): array
    {
        if (! preg_match('/^(\d{4})-Q([1-4])$/', $quarter, $matches)) {
            throw new InvalidArgumentException("Not a quarter label: {$quarter}");
        }

        $start = Carbon::create((int) $matches[1], ((int) $matches[2] - 1) * 3 + 1, 1)->startOfDay();

        return [$start, $start->copy()->addMonths(3)->subDay()->endOfDay()];
    }

    /**
     * Every VAT leg posted by ChargeVisit/RecordPurchase has a Sales or
     * Expenses sibling, so $net is only null if something else ever
     * posts to the VAT journal — the row then shows £0.00 net rather
     * than breaking the report.
     *
     * @return array{date: Carbon, memo: string, reference: mixed, net: Money, vat: Money}
     */
    private static function row(JournalTransaction $vatLeg, Money $vat, ?Money $net): array
    {
        return [
            'date' => $vatLeg->post_date,
            'memo' => $vatLeg->memo,
            'reference' => $vatLeg->reference,
            'net' => $net ?? Books::money(0),
            'vat' => $vat,
        ];
    }

    private static function total(Collection $rows, string $key): Money
    {
        return $rows->reduce(fn (Money $sum, array $row) => $sum->add($row[$key]), Books::money(0));
    }
}
