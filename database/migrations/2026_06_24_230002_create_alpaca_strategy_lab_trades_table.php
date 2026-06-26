<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alpaca_strategy_lab_trades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alpaca_strategy_lab_session_id');
            $table->string('symbol', 20);
            $table->string('direction', 10);
            $table->enum('side', ['buy', 'sell']);
            $table->enum('status', ['pending', 'open', 'closing', 'closed', 'cancelled', 'failed'])->default('pending');

            $table->string('entry_order_id')->nullable();
            $table->string('exit_order_id')->nullable();
            $table->timestamp('entry_time')->nullable();
            $table->decimal('entry_price', 14, 4)->nullable();
            $table->decimal('quantity', 14, 6)->nullable();
            $table->decimal('notional', 14, 4)->nullable();
            $table->decimal('stop_loss', 14, 4)->nullable();
            $table->decimal('take_profit', 14, 4)->nullable();

            $table->timestamp('exit_time')->nullable();
            $table->decimal('exit_price', 14, 4)->nullable();
            $table->string('exit_reason', 64)->nullable();
            $table->decimal('pnl', 14, 4)->nullable();
            $table->decimal('pnl_pct', 12, 4)->nullable();

            $table->json('signal_data')->nullable();
            $table->json('entry_order_payload')->nullable();
            $table->json('exit_order_payload')->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamp('last_sync_at')->nullable();

            $table->timestamps();

            $table->foreign('alpaca_strategy_lab_session_id', 'asl_trades_session_fk')
                ->references('id')
                ->on('alpaca_strategy_lab_sessions')
                ->cascadeOnDelete();
            $table->index(['alpaca_strategy_lab_session_id', 'status'], 'asl_trades_session_status_idx');
            $table->index(['symbol', 'status'], 'asl_trades_symbol_status_idx');
            $table->index('entry_order_id', 'asl_trades_entry_order_idx');
            $table->index('exit_order_id', 'asl_trades_exit_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alpaca_strategy_lab_trades');
    }
};
