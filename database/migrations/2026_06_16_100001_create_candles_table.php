<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candles', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20);
            $table->string('timeframe', 5);         // 1m, 5m, 15m
            $table->timestamp('opens_at');           // UTC datetime of the candle
            $table->decimal('open', 12, 4);
            $table->decimal('high', 12, 4);
            $table->decimal('low', 12, 4);
            $table->decimal('close', 12, 4);
            $table->bigInteger('volume')->default(0);
            $table->timestamps();

            $table->unique(['symbol', 'timeframe', 'opens_at'], 'candles_unique');
            $table->index(['symbol', 'timeframe', 'opens_at'], 'candles_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candles');
    }
};
