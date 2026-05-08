<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('symbol_id')->constrained()->cascadeOnDelete();
            $table->decimal('last_price', 12, 4);
            $table->decimal('bid', 12, 4)->nullable();
            $table->decimal('ask', 12, 4)->nullable();
            $table->decimal('open', 12, 4)->nullable();
            $table->decimal('high', 12, 4)->nullable();
            $table->decimal('low', 12, 4)->nullable();
            $table->decimal('close', 12, 4)->nullable();
            $table->decimal('change', 12, 4)->nullable();
            $table->decimal('change_percent', 8, 4)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->bigInteger('avg_volume')->nullable();
            $table->decimal('market_cap', 20, 2)->nullable();
            $table->decimal('pe_ratio', 10, 2)->nullable();
            $table->decimal('week_52_high', 12, 4)->nullable();
            $table->decimal('week_52_low', 12, 4)->nullable();
            $table->timestamp('quote_time')->nullable();
            $table->timestamps();
            
            $table->index('symbol_id');
            $table->index('quote_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
