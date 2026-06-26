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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('symbol_id')->constrained()->cascadeOnDelete();
            $table->enum('alert_type', ['unusual_premium', 'volume_spike', 'delta_threshold', 'price_movement', 'iv_spike']);
            $table->string('condition');
            $table->decimal('threshold_value', 12, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_triggered')->default(false);
            $table->timestamp('triggered_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('symbol_id');
            $table->index('alert_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
