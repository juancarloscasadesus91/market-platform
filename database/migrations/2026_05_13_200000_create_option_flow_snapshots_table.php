<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_flow_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20);
            $table->decimal('strike', 10, 2);
            $table->string('option_type', 4); // CALL or PUT
            $table->date('expiration_date');
            $table->string('time_window', 10); // 1m, 5m, 15m, day
            $table->timestamp('window_start');
            $table->timestamp('window_end');

            // Premium metrics
            $table->decimal('total_premium', 15, 2)->default(0);
            $table->decimal('buy_premium', 15, 2)->default(0);
            $table->decimal('sell_premium', 15, 2)->default(0);
            $table->decimal('mid_premium', 15, 2)->default(0);

            // Trade counts
            $table->integer('buy_trades')->default(0);
            $table->integer('sell_trades')->default(0);
            $table->integer('mid_trades')->default(0);

            // Volume metrics
            $table->integer('buy_volume')->default(0);
            $table->integer('sell_volume')->default(0);
            $table->integer('mid_volume')->default(0);

            // Aggressiveness
            $table->decimal('avg_aggressiveness', 5, 3)->default(0);
            $table->decimal('mid_noise_ratio', 5, 3)->default(0);

            // Directional scoring
            $table->decimal('directional_score', 15, 2)->default(0);
            $table->string('confidence_level', 20)->default('UNKNOWN'); // HIGH, MEDIUM, LOW, UNKNOWN

            $table->timestamps();

            // Indexes for performance
            $table->index(['symbol', 'window_start']);
            $table->index(['symbol', 'strike', 'expiration_date']);
            $table->index(['time_window', 'window_start']);
            $table->index(['directional_score']);
            $table->index(['confidence_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_flow_snapshots');
    }
};
