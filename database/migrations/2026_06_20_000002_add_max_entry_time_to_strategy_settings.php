<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->string('max_entry_time')->nullable()->after('force_exit_time');
            // Format: "HH:MM" ET (e.g., "14:00" for 2 PM ET)
        });
    }

    public function down(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->dropColumn('max_entry_time');
        });
    }
};
