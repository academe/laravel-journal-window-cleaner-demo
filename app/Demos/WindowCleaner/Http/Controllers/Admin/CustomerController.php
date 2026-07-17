<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use App\Demos\WindowCleaner\Actions\ChargeVisit;
use App\Demos\WindowCleaner\Actions\RecordPayment;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Support\Gbp;
use App\Demos\WindowCleaner\Support\Statement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerController
{
    public function index(): View
    {
        return view('demos.window-cleaner.admin.customers.index', [
            'customers' => Customer::query()->orderBy('name')->get(),
        ]);
    }

    public function show(Customer $customer): View
    {
        return view('demos.window-cleaner.admin.customers.show', [
            'customer' => $customer,
            'plans' => $customer->servicePlans()->with('service')->get(),
            'statement' => Statement::for($customer->journal),
            'services' => Service::query()->orderBy('name')->get(),
        ]);
    }

    public function storeVisit(Request $request, Customer $customer, ChargeVisit $chargeVisit): RedirectResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'exists:wc_services,id'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000'],
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $chargeVisit->run($customer, $service, Gbp::parse((string) $validated['price']));

        return redirect()
            ->route('wc.admin.customers.show', $customer)
            ->with('status', "Visit recorded: {$service->name}.");
    }

    public function storePayment(Request $request, Customer $customer, RecordPayment $recordPayment): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000'],
        ]);

        $payment = $recordPayment->run($customer, Gbp::parse((string) $validated['amount']), null, 'manual');

        return redirect()
            ->route('wc.admin.customers.show', $customer)
            ->with('status', 'Payment recorded: '.Gbp::format($payment->amountAsMoney()).'.');
    }
}
