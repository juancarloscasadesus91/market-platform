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
        Schema::create('trading_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->decimal('capital_inicial', 15, 2);
            $table->integer('num_trades')->default(2);
            $table->decimal('profit_percent', 8, 2);
            $table->decimal('profit_diario', 15, 2);
            $table->string('formula')->nullable();
            $table->decimal('capital_final', 15, 2);
            $table->decimal('capital_real', 15, 2);
            $table->timestamps();
            
            $table->index('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_journal_entries');
    }
};
