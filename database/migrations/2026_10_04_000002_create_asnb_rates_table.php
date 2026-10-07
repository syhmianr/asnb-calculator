<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asnb_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asnb_fund_id')->constrained('asnb_funds')->cascadeOnDelete();
            $table->unsignedSmallInteger('financial_year');
            $table->decimal('dividend_rate', 6, 3);
            $table->decimal('bonus_rate', 6, 3)->default(0);
            // minimum_monthly_balance | average_monthly_balance | custom
            $table->string('calculation_method')->default('minimum_monthly_balance');
            // Optional cap (RM) on the balance eligible for bonus. Null = no cap.
            $table->decimal('bonus_cap', 14, 2)->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['asnb_fund_id', 'financial_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asnb_rates');
    }
};
