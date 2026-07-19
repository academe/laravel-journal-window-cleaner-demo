<?php

return [
    // The single currency every journal in the demo uses. Default GBP;
    // US-based developers can set DEMO_CURRENCY=USD in .env. Journals
    // store their currency, so changing this on a seeded database will
    // provoke CurrencyMismatch everywhere — rebuild with
    // `php artisan migrate:fresh --seed` after switching.
    'currency' => env('DEMO_CURRENCY', 'GBP'),

    // Standard VAT/sales-tax rate, percent (UK standard rate 20).
    // Consumer prices in the demo are tax-inclusive; Vat::split()
    // extracts the tax portion at this rate.
    'vat_rate_percent' => 20,
];
