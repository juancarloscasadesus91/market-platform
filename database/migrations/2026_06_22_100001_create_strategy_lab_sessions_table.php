<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_lab_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('strategy_key', 64);           // e.g. 'ema_pullback'
            $table->json('symbols');
            $table->string('timeframe', 5);
            $table->date('date_from');
            $table->date('date_to');
            $table->json('params');                        // full strategy params snapshot

            $table->enum('status', ['pending', 'importing', 'running', 'completed', 'failed'])->default('pending');
            $table->longText('error_message')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('progress_label')->nullable();

            // Summary stats
            $table->unsignedInteger('total_candles')->default(0);
            $table->unsignedInteger('total_signals')->default(0);
            $table->unsignedInteger('total_trades')->default(0);
            $table->unsignedInteger('winning_trades')->default(0);
            $table->unsignedInteger('losing_trades')->default(0);
            $table->unsignedInteger('breakeven_trades')->default(0);
            $table->decimal('win_rate', 5, 2)->nullable();
            $table->decimal('profit_factor', 10, 4)->nullable();
            $table->decimal('total_pnl_points', 12, 4)->nullable();
            $table->decimal('total_pnl_pct', 12, 4)->nullable();
            $table->decimal('max_drawdown', 12, 4)->nullable();
            $table->decimal('avg_winner_pts', 12, 4)->nullable();
            $table->decimal('avg_loser_pts', 12, 4)->nullable();
            $table->string('best_hour')->nullable();
            $table->string('worst_hour')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('strategy_key');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_lab_sessions');
    }
};
