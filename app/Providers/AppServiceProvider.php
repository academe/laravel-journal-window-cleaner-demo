<?php

namespace App\Providers;

use App\Demos\WindowCleaner\Models\CompanyAccount;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Payment;
use App\Demos\WindowCleaner\Models\Purchase;
use App\Demos\WindowCleaner\Models\Visit;
use App\Demos\WindowCleaner\Models\Wallet;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Store short aliases instead of namespaced class names in the
        // polymorphic type columns the journal package writes: journal
        // owners land in journals.owner_type (Customer, CompanyAccount,
        // Wallet) and transaction references in
        // journal_transactions.reference_type (Visit, Payment). Aliases
        // keep those columns readable when inspecting the database and
        // stable if a model is ever renamed or moved. enforceMorphMap
        // throws for any unmapped model, so new morph usage cannot
        // silently fall back to a class name.
        Relation::enforceMorphMap([
            'customer' => Customer::class,
            'company-account' => CompanyAccount::class,
            'wallet' => Wallet::class,
            'visit' => Visit::class,
            'payment' => Payment::class,
            'purchase' => Purchase::class,
        ]);
    }
}
