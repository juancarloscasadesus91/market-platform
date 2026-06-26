<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_position_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20);
            $table->decimal('strike', 10, 2);
            $table->string('option_type', 4); // CALL or PUT
            $table->date('expiration_date');
            $table->timestamp('snapshot_time');

            // Position estimation metrics
            $table->decimal('estimated_open_premium', 15, 2)->default(0);
            $table->decimal('estimated_close_premium', 15, 2)->default(0);
            $table->decimal('estimated_remaining_premium', 15, 2)->default(0);

            // Price tracking
            $table->decimal('avg_entry_price', 8, 3)->default(0);
            $table->decimal('avg_exit_price', 8, 3)->default(0);
            $table->decimal('current_mark', 8, 3)->default(0);
            $table->decimal('unrealized_pnl_estimate', 15, 2)->default(0);

            // Position metrics
            $table->decimal('potential_exit_pressure', 5, 3)->default(0); // 0-1 scale
            $table->decimal('position_confidence', 5, 3)->default(0); // 0-1 scale
            $table->string('position_status', 30)->default('UNKNOWN'); // BUILDING, HOLDING, EXITING, CLOSED

            // MID tracking (doesn't affect positions directly)
            $table->decimal('mid_premium', 15, 2)->default(0);
            $table->integer('mid_trade_count')->default(0);
            $table->decimal('mid_leaning_buy_premium', 15, 2)->default(0);
            $table->decimal('mid_leaning_sell_premium', 15, 2)->default(0);

            $table->timestamps();

            // Indexes for performance
            $table->index(['symbol', 'strike', 'expiration_date']);
            $table->index(['snapshot_time']);
            $table->index(['estimated_remaining_premium']);
            $table->index(['position_status']);
            $table->index(['potential_exit_pressure']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_position_snapshots');
    }
};
