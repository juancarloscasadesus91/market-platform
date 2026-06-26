<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->decimal('rsi_max_call', 5, 2)->nullable()->change();
            $table->decimal('rsi_min_put', 5, 2)->nullable()->change();
            $table->decimal('max_candle_atr_ratio', 4, 2)->nullable()->change();
            $table->decimal('max_price_ema_dist_pct', 8, 4)->nullable()->change();
            $table->decimal('min_bb_dist_pct', 8, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->decimal('rsi_max_call', 5, 2)->nullable(false)->default(70.00)->change();
            $table->decimal('rsi_min_put', 5, 2)->nullable(false)->default(30.00)->change();
            $table->decimal('max_candle_atr_ratio', 4, 2)->nullable(false)->default(2.00)->change();
            $table->decimal('max_price_ema_dist_pct', 8, 4)->nullable(false)->default(2.00)->change();
            $table->decimal('min_bb_dist_pct', 8, 4)->nullable(false)->default(0.10)->change();
        });
    }
};
