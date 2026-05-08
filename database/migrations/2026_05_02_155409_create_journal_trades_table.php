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
        Schema::create('journal_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_journal_entry_id')->constrained()->onDelete('cascade');
            $table->string('symbol'); // Símbolo operado (ej: SPY, AAPL)
            $table->decimal('strike_price', 10, 2); // Precio de strike
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_trades');
    }
};
