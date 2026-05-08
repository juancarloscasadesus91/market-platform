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
        Schema::create('symbols', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 10)->unique();
            $table->string('name');
            $table->string('exchange', 20)->nullable();
            $table->string('sector', 50)->nullable();
            $table->string('industry', 100)->nullable();
            $table->decimal('market_cap', 20, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('ticker');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('symbols');
    }
};
