<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alpaca_strategy_lab_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alpaca_strategy_lab_session_id');
            $table->unsignedBigInteger('alpaca_strategy_lab_trade_id')->nullable();
            $table->string('level', 16)->default('info');
            $table->string('event', 64);
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->foreign('alpaca_strategy_lab_session_id', 'asl_logs_session_fk')
                ->references('id')
                ->on('alpaca_strategy_lab_sessions')
                ->cascadeOnDelete();
            $table->foreign('alpaca_strategy_lab_trade_id', 'asl_logs_trade_fk')
                ->references('id')
                ->on('alpaca_strategy_lab_trades')
                ->nullOnDelete();
            $table->index(['alpaca_strategy_lab_session_id', 'created_at'], 'asl_logs_session_created_idx');
            $table->index(['level', 'event'], 'asl_logs_level_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alpaca_strategy_lab_logs');
    }
};
