<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stand-in owner models for pure accounting accounts (Sales, VAT,
        // Bank). A journal must be owned by a model; these rows exist to
        // own the business-side journals. See "Why journals are owned by
        // models" in the package README.
        Schema::create('wc_company_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wc_company_accounts');
    }
};
