<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alpaca_strategy_lab_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('symbol', 20);
            $table->string('timeframe', 5);
            $table->string('strategy_key', 64);
            $table->json('params');
            $table->enum('status', ['idle', 'running', 'paused', 'stopped', 'failed'])->default('idle');
            $table->string('mode', 16)->default('paper');

            $table->enum('position_size_type', ['fixed_qty', 'fixed_notional'])->default('fixed_qty');
            $table->decimal('position_size_value', 14, 4)->default(1);
            $table->unsignedTinyInteger('max_concurrent_trades')->default(1);
            $table->decimal('stop_loss_pct', 8, 4)->nullable();
            $table->decimal('take_profit_pct', 8, 4)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_signal_at')->nullable();
            $table->longText('error_message')->nullable();

            $table->unsignedInteger('total_trades')->default(0);
            $table->unsignedInteger('winning_trades')->default(0);
            $table->unsignedInteger('losing_trades')->default(0);
            $table->decimal('total_pnl', 14, 4)->default(0);
            $table->decimal('total_pnl_pct', 12, 4)->default(0);

            $table->timestamps();

            $table->index(['status', 'mode'], 'asl_sessions_status_mode_idx');
            $table->index(['symbol', 'timeframe'], 'asl_sessions_symbol_tf_idx');
            $table->index('strategy_key', 'asl_sessions_strategy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alpaca_strategy_lab_sessions');
    }
};
