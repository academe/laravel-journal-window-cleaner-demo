<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A purchase of supplies or equipment, paid at the till — no
        // supplier accounts. Journal entries that posted it point back
        // here via the package's `reference` morph, just like visits.
        Schema::create('wc_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('supplier');
            $table->string('category', 20);
            $table->unsignedInteger('price');
            $table->date('purchased_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wc_purchases');
    }
};
