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
            $table->decimal('fee', 10, 2)->default(1.80)->after('ganancia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_trades', function (Blueprint $table) {
            $table->dropColumn('fee');
        });
    }
};
