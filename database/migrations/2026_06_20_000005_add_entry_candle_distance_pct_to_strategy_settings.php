<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->decimal('entry_candle_distance_pct', 5, 2)->nullable()->after('max_entry_time');
            // Minimum candle range percentage for entry (e.g., 0.10 for 0.1%)
        });
    }

    public function down(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->dropColumn('entry_candle_distance_pct');
        });
    }
};
