<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Portal;

use App\Demos\WindowCleaner\Actions\RecordPayment;
use App\Demos\WindowCleaner\Models\Payment;
use App\Demos\WindowCleaner\Support\CurrentCustomer;
use App\Demos\WindowCleaner\Support\Gbp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;

class PaymentController
{
    public function create(): View|RedirectResponse
    {
        $customer = CurrentCustomer::get();

        if ($customer === null) {
            return redirect()->route('wc.portal.switch');
        }

        $owed = $customer->amountOwed();

        return view('demos.window-cleaner.portal.pay', [
            'customer' => $customer,
            'owed' => $owed,
            // Prefill as a plain decimal ("15.00") for the input value.
            // When nothing is owed, "0.00" would fail the min:0.01
            // validation on submit, so leave the field empty instead
            // (the placeholder still shows an example) and make the
            // customer type an amount.
            'suggested' => $owed->isZero()
                ? ''
                : (new DecimalMoneyFormatter(new ISOCurrencies))->format($owed),
        ]);
    }

    public function store(Request $request, RecordPayment $recordPayment): RedirectResponse
    {
        $customer = CurrentCustomer::get();

        if ($customer === null) {
            return redirect()->route('wc.portal.switch');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000'],
        ]);

        $payment = $recordPayment->run($customer, Gbp::parse((string) $validated['amount']));

        return redirect()->route('wc.portal.paid', $payment);
    }

    public function show(Payment $payment): View|RedirectResponse
    {
        $customer = CurrentCustomer::get();

        if ($customer === null) {
            return redirect()->route('wc.portal.switch');
        }

        if ($payment->customer_id !== $customer->id) {
            abort(404);
        }

        return view('demos.window-cleaner.portal.paid', [
            'payment' => $payment,
            'customer' => $payment->customer,
        ]);
    }
}
