<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // EMA lengths
            $table->unsignedSmallInteger('ema_fast')->default(21);
            $table->unsignedSmallInteger('ema_mid')->default(50);
            $table->unsignedSmallInteger('ema_slow')->default(100);

            // Strategy parameters
            $table->decimal('min_distance_pct', 8, 4)->default(0.02);
            $table->unsignedTinyInteger('max_bars_after_pullback')->default(3);

            // Indicator periods
            $table->unsignedSmallInteger('rsi_period')->default(14);
            $table->unsignedSmallInteger('bb_period')->default(20);
            $table->decimal('bb_stddev', 4, 2)->default(2.00);
            $table->unsignedSmallInteger('atr_period')->default(14);
            $table->unsignedSmallInteger('volume_avg_period')->default(20);

            // Filters
            $table->decimal('rsi_max_call', 5, 2)->default(70.00);   // max RSI for CALL entry
            $table->decimal('rsi_min_put', 5, 2)->default(30.00);    // min RSI for PUT entry
            $table->decimal('max_candle_atr_ratio', 4, 2)->default(2.00);
            $table->decimal('max_price_ema_dist_pct', 8, 4)->default(2.00);
            $table->decimal('min_bb_dist_pct', 8, 4)->default(0.10);

            // Stop loss
            $table->enum('stop_type', ['pullback', 'ema_mid', 'atr'])->default('pullback');
            $table->decimal('stop_atr_mult', 4, 2)->default(1.50);
            $table->decimal('stop_buffer_pct', 6, 4)->default(0.05);

            // Take profit
            $table->enum('tp_type', ['risk_ratio', 'points', 'percent'])->default('risk_ratio');
            $table->decimal('tp1_value', 6, 2)->default(1.00);  // R or points or %
            $table->decimal('tp2_value', 6, 2)->default(2.00);
            $table->decimal('tp3_value', 6, 2)->default(3.00);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_settings');
    }
};
