<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use Academe\LaravelJournal\Models\JournalTransaction;
use App\Demos\WindowCleaner\Support\Books;
use Illuminate\Contracts\View\View;

class BooksController
{
    public function show(): View
    {
        $debtors = Books::debtorsLedger()->normalBalanceOn(Books::currencyCode());
        $bank = Books::bankLedger()->normalBalanceOn(Books::currencyCode());
        $sales = Books::salesLedger()->normalBalanceOn(Books::currencyCode());
        $vat = Books::vatLedger()->normalBalanceOn(Books::currencyCode());
        $expenses = Books::expensesLedger()->normalBalanceOn(Books::currencyCode());

        // Recent groups, newest first: find the 10 most recent distinct
        // group UUIDs (by their latest entry's post_date/created_at),
        // then load every entry for those groups — never a partial
        // group — so this page always shows debits balancing credits.
        $recentGroupUuids = JournalTransaction::query()
            ->whereNotNull('transaction_group')
            ->selectRaw('transaction_group, MAX(post_date) as latest_post_date, MAX(created_at) as latest_created_at')
            ->groupBy('transaction_group')
            ->orderByDesc('latest_post_date')
            ->orderByDesc('latest_created_at')
            ->limit(10)
            ->pluck('transaction_group');

        $entriesByGroup = JournalTransaction::query()
            ->whereIn('transaction_group', $recentGroupUuids)
            ->with('journal.owner')
            ->orderByDesc('post_date')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('transaction_group');

        $recentGroups = $recentGroupUuids->mapWithKeys(
            fn (string $uuid) => [$uuid => $entriesByGroup->get($uuid)]
        );

        return view('demos.window-cleaner.admin.books', [
            'debtors' => $debtors,
            'bank' => $bank,
            'sales' => $sales,
            'vat' => $vat,
            'expenses' => $expenses,
            'assets' => $debtors->add($bank),
            'liabilitiesPlusIncomeLessExpenses' => $vat->add($sales)->subtract($expenses),
            'recentGroups' => $recentGroups,
        ]);
    }
}
