<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->decimal('stop_pct', 5, 2)->nullable()->after('stop_buffer_pct');
            // Percentage of entry price for stop loss (e.g., 0.50 for 0.5%)
        });
    }

    public function down(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->dropColumn('stop_pct');
        });
    }
};
