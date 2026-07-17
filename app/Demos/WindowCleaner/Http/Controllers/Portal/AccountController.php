<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Portal;

use App\Demos\WindowCleaner\Support\CurrentCustomer;
use App\Demos\WindowCleaner\Support\Statement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AccountController
{
    public function show(): View|RedirectResponse
    {
        $customer = CurrentCustomer::get();

        if ($customer === null) {
            return redirect()->route('wc.portal.switch');
        }

        return view('demos.window-cleaner.portal.account', [
            'customer' => $customer,
            'plans' => $customer->servicePlans()->where('active', true)->with('service')->get(),
            'statement' => Statement::for($customer->journal),
        ]);
    }
}
