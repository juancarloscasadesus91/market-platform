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
        Schema::create('portfolio_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('initial_value', 15, 2)->default(0);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->timestamps();
        });
        
        // Insert default record
        DB::table('portfolio_settings')->insert([
            'initial_value' => 280.00,
            'current_value' => 280.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_settings');
    }
};
