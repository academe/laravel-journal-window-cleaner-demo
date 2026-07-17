<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wc_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('phone');
            $table->timestamps();
        });

        Schema::create('wc_services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // A customer's subscription to one service: their own price
        // (VAT-inclusive minor units), their own cadence, their own day.
        Schema::create('wc_service_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wc_customers');
            $table->foreignId('service_id')->constrained('wc_services');
            $table->unsignedInteger('price');
            $table->unsignedTinyInteger('interval_weeks');
            $table->date('next_due_on');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wc_service_plans');
        Schema::dropIfExists('wc_services');
        Schema::dropIfExists('wc_customers');
    }
};
