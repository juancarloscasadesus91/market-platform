<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_bot_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_bot_id')->constrained()->cascadeOnDelete();

            $table->string('symbol');
            $table->enum('direction', ['CALL', 'PUT', 'LONG', 'SHORT']);
            $table->enum('status', ['open', 'closed', 'cancelled'])->default('open');

            // Entry
            $table->timestamp('entry_time')->nullable();
            $table->decimal('entry_price', 12, 4);
            $table->decimal('quantity', 10, 4)->default(1);
            $table->decimal('entry_value', 12, 2)->default(0);   // entry_price * quantity

            // Exit
            $table->timestamp('exit_time')->nullable();
            $table->decimal('exit_price', 12, 4)->nullable();
            $table->string('exit_reason')->nullable();            // tp1/tp2/tp3/stop/time/manual

            // Risk levels
            $table->decimal('stop_loss', 12, 4)->nullable();
            $table->decimal('take_profit_1', 12, 4)->nullable();
            $table->decimal('take_profit_2', 12, 4)->nullable();
            $table->decimal('take_profit_3', 12, 4)->nullable();

            // P&L
            $table->decimal('pnl', 12, 2)->nullable();
            $table->decimal('pnl_pct', 8, 4)->nullable();
            $table->decimal('commission', 6, 2)->default(0);

            // Signal context
            $table->json('signal_data')->nullable();

            // Schwab order IDs (null in paper mode)
            $table->string('schwab_order_id')->nullable();
            $table->string('schwab_exit_order_id')->nullable();

            $table->timestamps();

            $table->index(['strategy_bot_id', 'status']);
            $table->index('entry_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_bot_trades');
    }
};
