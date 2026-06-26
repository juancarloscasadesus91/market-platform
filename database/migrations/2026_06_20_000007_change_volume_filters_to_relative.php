<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            // Change from absolute volume to relative volume (multiplier of avg volume)
            $table->dropColumn(['volume_min', 'volume_max']);
            $table->decimal('volume_rel_min', 5, 2)->nullable()->after('entry_candle_distance_pct');
            $table->decimal('volume_rel_max', 5, 2)->nullable()->after('volume_rel_min');
            // Relative volume: entry candle volume / avg volume of last N candles
            // Example: 0.5 = 50% of avg, 1.5 = 150% of avg, 2.0 = 200% of avg
        });
    }

    public function down(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->dropColumn(['volume_rel_min', 'volume_rel_max']);
            $table->unsignedBigInteger('volume_min')->nullable()->after('entry_candle_distance_pct');
            $table->unsignedBigInteger('volume_max')->nullable()->after('volume_min');
        });
    }
};
