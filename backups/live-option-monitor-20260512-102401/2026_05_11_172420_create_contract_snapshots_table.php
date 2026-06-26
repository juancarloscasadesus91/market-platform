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
        Schema::create('contract_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 50)->index();
            $table->string('underlying', 10)->index();
            $table->date('expiration_date')->index();
            $table->integer('dte')->index();
            $table->decimal('strike', 10, 2);
            $table->enum('type', ['C', 'P']);

            // Volume data
            $table->bigInteger('total_volume')->default(0);
            $table->bigInteger('volume_change')->default(0);

            // Premium data (in cents, multiply by 100)
            $table->bigInteger('total_premium')->default(0);
            $table->bigInteger('buy_premium')->default(0);
            $table->bigInteger('sell_premium')->default(0);
            $table->bigInteger('net_premium')->default(0);

            // Price data
            $table->decimal('last_price', 10, 4)->nullable();
            $table->decimal('bid', 10, 4)->nullable();
            $table->decimal('ask', 10, 4)->nullable();
            $table->decimal('mark', 10, 4)->nullable();

            // Greeks
            $table->decimal('delta', 8, 6)->nullable();
            $table->decimal('gamma', 8, 6)->nullable();
            $table->decimal('theta', 8, 6)->nullable();
            $table->decimal('vega', 8, 6)->nullable();
            $table->decimal('iv', 8, 4)->nullable();

            // Metadata
            $table->integer('total_trades')->default(0);
            $table->timestamp('snapshot_at')->index();
            $table->timestamps();

            // Composite indexes for efficient queries
            $table->index(['underlying', 'dte', 'snapshot_at']);
            $table->index(['symbol', 'snapshot_at']);
            $table->index(['expiration_date', 'snapshot_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_snapshots');
    }
};
