<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE alpaca_strategy_lab_trades MODIFY symbol VARCHAR(32) NOT NULL');
            return;
        }

        Schema::table('alpaca_strategy_lab_trades', function (Blueprint $table) {
            $table->string('symbol', 32)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE alpaca_strategy_lab_trades MODIFY symbol VARCHAR(20) NOT NULL');
            return;
        }

        Schema::table('alpaca_strategy_lab_trades', function (Blueprint $table) {
            $table->string('symbol', 20)->change();
        });
    }
};
