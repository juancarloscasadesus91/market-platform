<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trading_journal_entries', function (Blueprint $table) {
            $table->decimal('portfolio_value', 15, 2)->after('capital_real')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_journal_entries', function (Blueprint $table) {
            $table->dropColumn('portfolio_value');
        });
    }
};
