<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->float('min_ema21_ema50_dist')->nullable()->after('min_bb_dist_pct');
            $table->float('max_ema21_ema50_dist')->nullable()->after('min_ema21_ema50_dist');
            $table->float('min_ema50_ema100_dist')->nullable()->after('max_ema21_ema50_dist');
            $table->float('max_ema50_ema100_dist')->nullable()->after('min_ema50_ema100_dist');
        });
    }

    public function down(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->dropColumn([
                'min_ema21_ema50_dist',
                'max_ema21_ema50_dist',
                'min_ema50_ema100_dist',
                'max_ema50_ema100_dist',
            ]);
        });
    }
};
