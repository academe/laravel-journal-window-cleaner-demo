<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Portal;

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Support\CurrentCustomer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SwitchController
{
    public function index(): View
    {
        return view('demos.window-cleaner.portal.switch', [
            'customers' => Customer::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Customer $customer): RedirectResponse
    {
        CurrentCustomer::set($customer);

        return redirect()->route('wc.portal.account');
    }
}
