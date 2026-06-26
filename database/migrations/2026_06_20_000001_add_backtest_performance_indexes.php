<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backtest_trades', function (Blueprint $table) {
            // Index for entry_time ordering in equity curve calculations
            $table->index('entry_time');

            // Index for pnl_points aggregations
            $table->index('pnl_points');

            // Index for result filtering in pattern analysis
            $table->index('result');

            // Standalone index on backtest_session_id for faster lookups
            $table->index('backtest_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('backtest_trades', function (Blueprint $table) {
            $table->dropIndex(['entry_time']);
            $table->dropIndex(['pnl_points']);
            $table->dropIndex(['result']);
            $table->dropIndex(['backtest_session_id']);
        });
    }
};
