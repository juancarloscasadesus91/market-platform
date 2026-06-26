<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backtest_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backtest_session_id')->constrained('backtest_sessions')->cascadeOnDelete();
            $table->string('symbol', 20);
            $table->enum('direction', ['CALL', 'PUT']);

            // Pullback candle
            $table->timestamp('pullback_time');
            $table->decimal('pullback_open', 12, 4);
            $table->decimal('pullback_high', 12, 4);
            $table->decimal('pullback_low', 12, 4);
            $table->decimal('pullback_close', 12, 4);

            // Confirmation candle
            $table->timestamp('confirm_time');
            $table->decimal('confirm_open', 12, 4);
            $table->decimal('confirm_high', 12, 4);
            $table->decimal('confirm_low', 12, 4);
            $table->decimal('confirm_close', 12, 4);

            // Entry (next bar after confirmation)
            $table->timestamp('entry_time')->nullable();
            $table->decimal('entry_price', 12, 4)->nullable();       // open of entry bar

            // Indicators at confirmation bar
            $table->decimal('ema21', 12, 4);
            $table->decimal('ema50', 12, 4);
            $table->decimal('ema100', 12, 4);
            $table->decimal('min_distance', 12, 6);
            $table->decimal('dist_ema21_ema50', 12, 6);
            $table->decimal('dist_ema50_ema100', 12, 6);
            $table->decimal('rsi', 8, 4)->nullable();
            $table->decimal('atr', 12, 6)->nullable();
            $table->decimal('bb_upper', 12, 4)->nullable();
            $table->decimal('bb_middle', 12, 4)->nullable();
            $table->decimal('bb_lower', 12, 4)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->decimal('rel_volume', 8, 4)->nullable();

            // Trade levels
            $table->decimal('stop_loss', 12, 4)->nullable();
            $table->decimal('take_profit_1', 12, 4)->nullable();
            $table->decimal('take_profit_2', 12, 4)->nullable();
            $table->decimal('take_profit_3', 12, 4)->nullable();

            // Outcome
            $table->decimal('exit_price', 12, 4)->nullable();
            $table->timestamp('exit_time')->nullable();
            $table->enum('exit_reason', [
                'stop_loss', 'take_profit_1', 'take_profit_2', 'take_profit_3',
                'end_of_session', 'invalidation',
            ])->nullable();
            $table->enum('result', ['win', 'loss', 'breakeven', 'open'])->default('open');
            $table->decimal('pnl_points', 12, 4)->nullable();
            $table->decimal('pnl_pct', 8, 4)->nullable();
            $table->decimal('max_favorable_excursion', 12, 4)->nullable();
            $table->decimal('max_adverse_excursion', 12, 4)->nullable();
            $table->decimal('r_multiple', 8, 4)->nullable();         // pnl / risk

            $table->timestamps();

            $table->index(['backtest_session_id', 'symbol']);
            $table->index(['backtest_session_id', 'direction']);
            $table->index(['backtest_session_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtest_trades');
    }
};
