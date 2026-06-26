<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_bots', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('name');
            $table->string('strategy_key');          // ema_pullback | bollinger_rsi
            $table->string('symbol');                // e.g. SPY, QQQ, SPX
            $table->string('timeframe')->default('5m');

            // Mode
            $table->boolean('paper_mode')->default(true);
            $table->decimal('paper_budget', 12, 2)->default(10000.00);
            $table->decimal('paper_balance', 12, 2)->default(10000.00);   // running balance

            // Position sizing
            $table->enum('position_size_type', ['fixed_shares', 'fixed_dollars', 'risk_pct'])->default('fixed_dollars');
            $table->decimal('position_size_value', 12, 2)->default(1000.00);
            $table->decimal('risk_per_trade_pct', 6, 4)->nullable();      // used if risk_pct mode
            $table->unsignedTinyInteger('max_concurrent_trades')->default(1);
            $table->decimal('max_daily_loss_pct', 6, 4)->nullable();      // kill-switch % of budget

            // Status
            $table->enum('status', ['idle', 'running', 'paused', 'stopped'])->default('idle');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->string('stop_reason')->nullable();

            // Schwab account (only relevant when paper_mode = false)
            $table->string('schwab_account_hash')->nullable();

            // Strategy params stored as JSON (mirrors StrategyInterface::schema())
            $table->json('strategy_params');

            // Aggregated stats (denormalized for fast display)
            $table->unsignedInteger('total_trades')->default(0);
            $table->unsignedInteger('winning_trades')->default(0);
            $table->unsignedInteger('losing_trades')->default(0);
            $table->decimal('total_pnl', 12, 2)->default(0);
            $table->decimal('total_pnl_pct', 8, 4)->default(0);
            $table->decimal('max_drawdown', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_bots');
    }
};
