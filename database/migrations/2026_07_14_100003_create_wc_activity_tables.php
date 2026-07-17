<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One performed service. Journal entries that charged it point
        // back here via the package's `reference` morph.
        Schema::create('wc_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wc_customers');
            $table->foreignId('service_id')->constrained('wc_services');
            $table->foreignId('service_plan_id')->nullable()->constrained('wc_service_plans');
            $table->unsignedInteger('price');
            $table->date('visited_on');
            $table->timestamps();
        });

        Schema::create('wc_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wc_customers');
            $table->unsignedInteger('amount');
            $table->string('method', 20);
            $table->dateTime('paid_at');
            $table->timestamps();
        });

        // The fake SMS outbox (a stand-in for a real SMS provider).
        Schema::create('wc_sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wc_customers');
            $table->string('phone');
            $table->text('body');
            $table->dateTime('sent_at');
            $table->timestamps();
        });

        // Scratch owner model for the Tour's Playground page: Level A in
        // isolation, deliberately outside the business's books.
        Schema::create('wc_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wc_wallets');
        Schema::dropIfExists('wc_sms_messages');
        Schema::dropIfExists('wc_payments');
        Schema::dropIfExists('wc_visits');
    }
};
