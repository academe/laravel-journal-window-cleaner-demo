<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use Academe\LaravelJournal\Support\MoneyFormatter;
use App\Demos\WindowCleaner\Actions\RecordPurchase;
use App\Demos\WindowCleaner\Models\Purchase;
use App\Demos\WindowCleaner\Support\Books;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseController
{
    public function index(): View
    {
        return view('demos.window-cleaner.admin.purchases', [
            'purchases' => Purchase::query()
                ->orderByDesc('purchased_on')
                ->orderByDesc('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(Request $request, RecordPurchase $recordPurchase): RedirectResponse
    {
        $validated = $request->validate([
            'supplier' => ['required', 'string', 'max:100'],
            'category' => ['required', 'in:supplies,equipment'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:10000'],
        ]);

        $purchase = $recordPurchase->run(
            $validated['supplier'],
            $validated['category'],
            MoneyFormatter::parseDecimal($validated['price'], Books::currency()),
        );

        return redirect()
            ->route('wc.admin.purchases.index')
            ->with('status', 'Recorded '.MoneyFormatter::format($purchase->priceAsMoney())." {$purchase->category} purchase from {$purchase->supplier}.");
    }
}
