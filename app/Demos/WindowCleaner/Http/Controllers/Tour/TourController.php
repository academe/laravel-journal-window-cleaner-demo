<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Tour;

use Illuminate\Contracts\View\View;

/**
 * The guided tour: one page per package concept, each pointing at the
 * real file that implements it and the live page where it is running.
 */
class TourController
{
    public function show(string $page): View
    {
        $pages = $this->pages();

        abort_unless(array_key_exists($page, $pages), 404);

        return view('demos.window-cleaner.tour.show', [
            'current' => $page,
            'pages' => $pages,
        ] + $pages[$page]);
    }

    /**
     * @return array<string, array{title: string, intro: string, code: string, file: string, liveUrl: string, liveLabel: string}>
     */
    private function pages(): array
    {
        return [
            'level-a' => [
                'title' => 'Level A — a running balance per model',
                'intro' => 'Add the HasJournal trait to any Eloquent model and it owns a journal: '
                    .'post credits and debits, read balances. In this demo every Customer works '
                    .'this way — their account balance IS their journal. Payments are credits '
                    .'(positive), charges are debits (negative), so a negative balance means the '
                    .'customer owes money. Try the Playground to post raw entries on a scratch wallet.',
                'code' => <<<'PHP'
                    class Customer extends Model
                    {
                        use HasJournal;
                    }

                    $customer->initJournal('GBP');
                    $customer->journal->credit(Money::GBP(2000), 'Payment');
                    $customer->journal->debit(Money::GBP(1500), 'Full house clean');

                    $customer->journal->currentBalance();  // Money::GBP(500)
                    PHP,
                'file' => 'app/Demos/WindowCleaner/Models/Customer.php',
                'liveUrl' => '/window-cleaner/tour/playground',
                'liveLabel' => 'Try it on the Playground',
            ],
            'level-b' => [
                'title' => 'Level B — double entry with TransactionGroup',
                'intro' => 'Every charge and payment in this demo is a balanced TransactionGroup: '
                    .'debits must equal credits or nothing is written. Charging a £15.00 visit '
                    .'debits the customer the gross and credits Sales the net and VAT the tax — '
                    .'three legs, one atomic commit, every leg referencing the Visit row and '
                    .'tagged. The VAT-inclusive price is split using direct computation (VAT extracted at rate/(100+rate) rounded down, net is the remainder), so no '
                    .'penny is created or lost (try £14.99).',
                'code' => <<<'PHP'
                    ['net' => $net, 'vat' => $vat] = Vat::split($grossPrice);

                    TransactionGroup::make()
                        ->addTransaction($customer->journal, EntryType::Debit, $grossPrice, $memo, $visit, $date)
                        ->addTransaction(Books::salesJournal(), EntryType::Credit, $net, $memo, $visit, $date)
                        ->addTransaction(Books::vatJournal(), EntryType::Credit, $vat, "VAT on {$memo}", $visit, $date)
                        ->commit();
                    PHP,
                'file' => 'app/Demos/WindowCleaner/Actions/ChargeVisit.php',
                'liveUrl' => '/window-cleaner/admin/customers',
                'liveLabel' => 'Charge a visit from any customer page',
            ],
            'level-c' => [
                'title' => 'Level C — typed ledgers and the accounting equation',
                'intro' => 'Journals are grouped under typed ledgers: every customer journal sits '
                    .'in Debtors (asset), and the company journals in Bank (asset), Sales (income), '
                    .'VAT owed (liability) and Expenses (expense). Because every posting is a '
                    .'balanced group, Debtors + Bank always equals VAT owed + Sales − Expenses — '
                    .'the Books page computes each side with one Ledger::normalBalanceOn() call per '
                    .'ledger — signed from the ledger type\'s normal balance side, so assets read '
                    .'positive when debited — and shows the equation holding live. The VAT return page reads both '
                    .'sides of the one VAT journal back apart: credit legs are output VAT on '
                    .'sales, debit legs are input VAT on purchases.',
                'code' => <<<'PHP'
                    $debtors = Ledger::firstOrCreate(['name' => 'Debtors'], ['type' => StandardLedgerType::ASSET]);

                    $customer->initJournal(Books::currencyCode())->assignToLedger($debtors);

                    Books::debtorsLedger()->normalBalanceOn('GBP');  // one SQL aggregate
                    PHP,
                'file' => 'app/Demos/WindowCleaner/Actions/EnsureBooksExist.php',
                'liveUrl' => '/window-cleaner/admin/books',
                'liveLabel' => 'See the books balance live',
            ],
            'checkpoints' => [
                'title' => 'Checkpoints — fast balances and closed periods',
                'intro' => 'A checkpoint stores a journal\'s cumulative totals through a date and '
                    .'locks the period behind it: balance queries start from the checkpoint '
                    .'instead of scanning all history, and back-dated writes throw PeriodClosed. '
                    .'The admin Close month button checkpoints every journal through the last '
                    .'month end; the seeder closed the first month of history already, so this '
                    .'demo has been running on a checkpoint since you seeded it.',
                'code' => <<<'PHP'
                    $journal->checkpoint('2026-06-30');    // freeze through end of June

                    $journal->credit(Money::GBP(1000), 'too late', Carbon::parse('2026-06-20'));
                    // => throws PeriodClosed (inside a TransactionGroup it surfaces
                    //    as TransactionCouldNotBeProcessed with PeriodClosed chained)
                    PHP,
                'file' => 'app/Demos/WindowCleaner/Actions/CloseMonth.php',
                'liveUrl' => '/window-cleaner/admin/close-month',
                'liveLabel' => 'Close a month yourself',
            ],
        ];
    }
}
