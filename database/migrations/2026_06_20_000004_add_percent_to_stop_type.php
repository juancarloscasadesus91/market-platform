<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->enum('stop_type', ['pullback', 'ema_mid', 'atr', 'ema_mid_range', 'ema_quadrant_trailing', 'percent'])
                ->default('pullback')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->enum('stop_type', ['pullback', 'ema_mid', 'atr', 'ema_mid_range', 'ema_quadrant_trailing'])
                ->default('pullback')
                ->change();
        });
    }
};
