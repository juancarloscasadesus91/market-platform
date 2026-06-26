<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('volume_min')->nullable()->after('entry_candle_distance_pct');
            $table->unsignedBigInteger('volume_max')->nullable()->after('volume_min');
            // Volume filters for entry candle (absolute volume, not relative)
        });
    }

    public function down(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->dropColumn(['volume_min', 'volume_max']);
        });
    }
};
