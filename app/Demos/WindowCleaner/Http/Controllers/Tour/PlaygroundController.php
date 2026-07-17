<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Tour;

use Academe\LaravelJournal\Exceptions\CurrencyMismatch;
use App\Demos\WindowCleaner\Models\Wallet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Parser\DecimalMoneyParser;

/**
 * Level A in isolation: a scratch GBP wallet you can post raw credits
 * and debits to. Posting USD provokes the package's CurrencyMismatch —
 * the demo's one deliberately-broken button.
 */
class PlaygroundController
{
    public function show(): View
    {
        $wallet = $this->wallet();

        return view('demos.window-cleaner.tour.playground', [
            'balance' => $wallet->journal->currentBalance(),
            'transactions' => $wallet->journal->transactions()
                ->orderByDesc('post_date')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:10000'],
            'currency' => ['required', 'in:GBP,USD'],
            'memo' => ['nullable', 'string', 'max:100'],
        ]);

        $money = (new DecimalMoneyParser(new ISOCurrencies))
            ->parse((string) $validated['amount'], new Currency($validated['currency']));

        try {
            $this->wallet()->journal->{$validated['direction']}($money, $validated['memo'] ?? null);
        } catch (CurrencyMismatch $e) {
            return redirect()->route('wc.tour.playground')
                ->with('error', 'CurrencyMismatch: '.$e->getMessage());
        }

        return redirect()->route('wc.tour.playground')
            ->with('status', ucfirst($validated['direction']).' posted.');
    }

    private function wallet(): Wallet
    {
        $wallet = Wallet::firstOrCreate(['name' => 'playground']);

        if ($wallet->journal()->doesntExist()) {
            $wallet->initJournal('GBP');
        }

        return $wallet->load('journal');
    }
}
