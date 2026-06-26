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
        Schema::table('journal_trades', function (Blueprint $table) {
            $table->decimal('capital_usado', 10, 2)->after('strike_price')->default(0);
            $table->decimal('profit_percent', 8, 2)->after('capital_usado')->default(0);
            $table->decimal('ganancia', 10, 2)->after('profit_percent')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_trades', function (Blueprint $table) {
            $table->dropColumn(['capital_usado', 'profit_percent', 'ganancia']);
        });
    }
};
