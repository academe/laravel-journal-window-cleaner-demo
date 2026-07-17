<?php

namespace Database\Factories;

use App\Demos\WindowCleaner\Models\Customer;
use App\Demos\WindowCleaner\Models\Service;
use App\Demos\WindowCleaner\Models\ServicePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePlanFactory extends Factory
{
    protected $model = ServicePlan::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'service_id' => Service::factory(),
            'price' => 1500,
            'interval_weeks' => 2,
            'next_due_on' => today(),
            'active' => true,
        ];
    }
}
