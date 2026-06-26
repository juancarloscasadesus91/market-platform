<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->string('force_exit_time', 5)->nullable()->after('tp3_value');
        });
    }

    public function down(): void
    {
        Schema::table('strategy_settings', function (Blueprint $table) {
            $table->dropColumn('force_exit_time');
        });
    }
};
