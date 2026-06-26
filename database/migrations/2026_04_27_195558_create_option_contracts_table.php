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
        Schema::create('option_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('symbol_id')->constrained()->cascadeOnDelete();
            $table->string('contract_symbol', 50)->unique();
            $table->enum('option_type', ['call', 'put']);
            $table->decimal('strike', 12, 4);
            $table->date('expiration_date');
            $table->decimal('bid', 12, 4)->nullable();
            $table->decimal('ask', 12, 4)->nullable();
            $table->decimal('last', 12, 4)->nullable();
            $table->decimal('mark', 12, 4)->nullable();
            $table->integer('volume')->nullable();
            $table->integer('open_interest')->nullable();
            $table->decimal('delta', 8, 6)->nullable();
            $table->decimal('gamma', 8, 6)->nullable();
            $table->decimal('theta', 8, 6)->nullable();
            $table->decimal('vega', 8, 6)->nullable();
            $table->decimal('rho', 8, 6)->nullable();
            $table->decimal('implied_volatility', 8, 4)->nullable();
            $table->boolean('in_the_money')->default(false);
            $table->decimal('intrinsic_value', 12, 4)->nullable();
            $table->decimal('extrinsic_value', 12, 4)->nullable();
            $table->timestamps();
            
            $table->index('symbol_id');
            $table->index('option_type');
            $table->index('expiration_date');
            $table->index(['symbol_id', 'expiration_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_contracts');
    }
};
