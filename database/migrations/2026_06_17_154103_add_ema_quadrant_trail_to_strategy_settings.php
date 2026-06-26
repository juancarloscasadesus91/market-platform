<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE strategy_settings MODIFY tp_type ENUM('risk_ratio','points','percent','ema_quadrant_trail') NOT NULL DEFAULT 'risk_ratio'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE strategy_settings MODIFY tp_type ENUM('risk_ratio','points','percent') NOT NULL DEFAULT 'risk_ratio'");
    }
};
