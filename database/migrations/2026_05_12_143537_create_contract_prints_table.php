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
        Schema::create('contract_prints', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 50)->index();
            $table->timestamp('print_time')->index();
            $table->decimal('price', 10, 4);
            $table->integer('size');
            $table->enum('side', ['ASK', 'BID', 'MID']);
            $table->bigInteger('premium'); // in cents
            $table->bigInteger('cumulative_volume');
            $table->timestamps();

            // Composite index for efficient queries
            $table->index(['symbol', 'print_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_prints');
    }
};
