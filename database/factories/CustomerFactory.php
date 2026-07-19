<?php

namespace Database\Factories;

use App\Demos\WindowCleaner\Actions\EnsureBooksExist;
use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Support\Books;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'address' => fake()->streetAddress(),
            'phone' => '07700 900'.fake()->unique()->numberBetween(100, 999),
        ];
    }

    public function configure(): static
    {
        // Every customer needs a journal in the Debtors ledger; the books
        // must exist first. EnsureBooksExist is idempotent, so tests can
        // just use the factory with no extra setup.
        return $this->afterCreating(function (Customer $customer) {
            app(EnsureBooksExist::class)->run();
            $customer->initJournal(Books::currencyCode())->assignToLedger(Books::debtorsLedger());
        });
    }
}
