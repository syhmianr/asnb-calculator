<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculations', function (Blueprint $table) {
            $table->id();
            $table->string('share_token', 16)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asnb_fund_id')->constrained('asnb_funds')->cascadeOnDelete();
            $table->unsignedSmallInteger('financial_year');
            $table->decimal('starting_balance', 14, 2);
            $table->decimal('dividend_rate', 6, 3);
            $table->decimal('bonus_rate', 6, 3);
            $table->string('transaction_timing');
            $table->json('calculation_data');
            $table->decimal('estimated_dividend', 14, 2);
            $table->decimal('estimated_bonus', 14, 2);
            $table->decimal('estimated_profit', 14, 2);
            $table->decimal('ending_balance', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculations');
    }
};
