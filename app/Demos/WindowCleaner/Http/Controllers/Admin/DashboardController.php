<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\ServicePlan;
use App\Demos\WindowCleaner\Support\Books;
use App\Demos\WindowCleaner\Support\Gbp;
use Illuminate\Contracts\View\View;
use Money\Money;

class DashboardController
{
    public function show(): View
    {
        $duePlans = ServicePlan::query()
            ->where('active', true)
            ->whereDate('next_due_on', '<=', today())
            ->with(['customer', 'service'])
            ->orderBy('next_due_on')
            ->get();

        $totalOwed = Customer::query()->get()
            ->reduce(
                fn (Money $sum, Customer $customer) => $sum->add($customer->amountOwed()),
                Gbp::money(0),
            );

        return view('demos.window-cleaner.admin.dashboard', [
            'duePlans' => $duePlans,
            'totalOwed' => $totalOwed,
            'bankBalance' => Books::bankLedger()->currentBalance('GBP'),
        ]);
    }
}
