<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->string('min_entry_time')->nullable()->after('max_entry_time');
            // Format: "HH:MM" ET (e.g., "09:30" for 9:30 AM ET)
        });
    }

    public function down(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->dropColumn('min_entry_time');
        });
    }
};
