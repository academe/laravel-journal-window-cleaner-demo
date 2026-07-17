<?php

namespace Database\Factories;

use App\Demos\WindowCleaner\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        return [
            'supplier' => fake()->company(),
            'category' => 'supplies',
            'price' => 2394,
            'purchased_on' => today(),
        ];
    }
}
