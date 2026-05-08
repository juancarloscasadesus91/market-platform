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
            // Renombrar campos existentes a "_real"
            $table->renameColumn('capital_inicial', 'capital_inicial_real');
            $table->renameColumn('num_trades', 'num_trades_real');
            $table->renameColumn('profit_percent', 'profit_percent_real');
            $table->renameColumn('profit_diario', 'profit_diario_real');
            $table->renameColumn('formula', 'formula_real');
            $table->renameColumn('capital_final', 'capital_final_real');
        });
        
        Schema::table('trading_journal_entries', function (Blueprint $table) {
            // Agregar campos "_plan"
            $table->decimal('capital_inicial_plan', 15, 2)->after('fecha');
            $table->integer('num_trades_plan')->default(2)->after('capital_inicial_plan');
            $table->decimal('profit_percent_plan', 8, 2)->after('num_trades_plan');
            $table->decimal('profit_diario_plan', 15, 2)->after('profit_percent_plan');
            $table->string('formula_plan')->nullable()->after('profit_diario_plan');
            $table->decimal('capital_final_plan', 15, 2)->after('formula_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_journal_entries', function (Blueprint $table) {
            // Eliminar campos "_plan"
            $table->dropColumn([
                'capital_inicial_plan',
                'num_trades_plan',
                'profit_percent_plan',
                'profit_diario_plan',
                'formula_plan',
                'capital_final_plan',
            ]);
        });
        
        Schema::table('trading_journal_entries', function (Blueprint $table) {
            // Renombrar de vuelta
            $table->renameColumn('capital_inicial_real', 'capital_inicial');
            $table->renameColumn('num_trades_real', 'num_trades');
            $table->renameColumn('profit_percent_real', 'profit_percent');
            $table->renameColumn('profit_diario_real', 'profit_diario');
            $table->renameColumn('formula_real', 'formula');
            $table->renameColumn('capital_final_real', 'capital_final');
        });
    }
};
