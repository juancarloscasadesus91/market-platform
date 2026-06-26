<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_lab_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_lab_session_id')
                  ->constrained('strategy_lab_sessions')
                  ->cascadeOnDelete();
            $table->string('symbol', 20);
            $table->string('direction', 10);              // CALL/PUT/LONG/SHORT (strategy-defined)

            // Entry
            $table->timestamp('entry_time')->nullable();
            $table->decimal('entry_price', 12, 4)->nullable();

            // Exit
            $table->timestamp('exit_time')->nullable();
            $table->decimal('exit_price', 12, 4)->nullable();
            $table->string('exit_reason', 64)->nullable();
            $table->enum('result', ['win', 'loss', 'breakeven', 'open'])->default('open');

            // P&L
            $table->decimal('pnl_points', 12, 4)->nullable();
            $table->decimal('pnl_pct', 8, 4)->nullable();
            $table->decimal('max_favorable_excursion', 12, 4)->nullable();
            $table->decimal('max_adverse_excursion', 12, 4)->nullable();
            $table->decimal('r_multiple', 8, 4)->nullable();

            // Risk levels
            $table->decimal('stop_loss', 12, 4)->nullable();
            $table->decimal('take_profit_1', 12, 4)->nullable();
            $table->decimal('take_profit_2', 12, 4)->nullable();
            $table->decimal('take_profit_3', 12, 4)->nullable();

            // Strategy-specific signal data (flexible JSON blob)
            $table->json('signal_data')->nullable();

            $table->timestamps();

            $table->index(['strategy_lab_session_id', 'symbol']);
            $table->index(['strategy_lab_session_id', 'result']);
            $table->index(['strategy_lab_session_id', 'entry_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_lab_trades');
    }
};
