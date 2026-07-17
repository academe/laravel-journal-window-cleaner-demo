<?php

namespace App\Demos\WindowCleaner\Support;

use App\Demos\WindowCleaner\Models\Customer;

/**
 * The emulated "login": which customer the portal is acting as, held
 * in the session. Real authentication would be pure noise in a demo
 * about journals — this is the entire mechanism.
 */
final class CurrentCustomer
{
    private const KEY = 'wc_customer_id';

    public static function get(): ?Customer
    {
        $id = session(self::KEY);

        return $id === null ? null : Customer::find($id);
    }

    public static function set(Customer $customer): void
    {
        session([self::KEY => $customer->id]);
    }
}
