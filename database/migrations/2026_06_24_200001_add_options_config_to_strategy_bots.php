<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategy_bots', function (Blueprint $table) {
            // Trade type: equity (default, current behavior) or options
            $table->enum('trade_type', ['equity', 'options'])->default('equity')->after('timeframe');

            // Options contract selection
            $table->decimal('option_delta_target', 4, 2)->nullable()->after('trade_type');       // e.g. 0.40
            $table->decimal('option_delta_tolerance', 4, 2)->default(0.05)->after('option_delta_target'); // ±0.05
            $table->unsignedTinyInteger('option_max_dte')->default(7)->after('option_delta_tolerance');   // max days to expiry
            $table->unsignedTinyInteger('option_min_dte')->default(1)->after('option_max_dte');           // min DTE
            $table->unsignedSmallInteger('option_contracts')->default(1)->after('option_min_dte');        // number of contracts

            // Option exit rules (% of contract value, null = disabled)
            $table->decimal('option_stop_loss_pct', 6, 2)->nullable()->after('option_contracts');    // e.g. 50 = exit if contract loses 50%
            $table->decimal('option_take_profit_pct', 6, 2)->nullable()->after('option_stop_loss_pct'); // e.g. 100 = exit if contract gains 100%

            // Option order type
            $table->enum('option_order_type', ['market', 'limit', 'mid'])->default('mid')->after('option_take_profit_pct');
            $table->decimal('option_limit_offset', 4, 2)->default(0.05)->after('option_order_type'); // offset from mid for limit
        });

        Schema::table('strategy_bot_trades', function (Blueprint $table) {
            // Option contract details stored when trade opens
            $table->string('option_contract_symbol')->nullable()->after('symbol');  // e.g. SPY 250620C00590000
            $table->decimal('option_entry_price', 10, 4)->nullable()->after('option_contract_symbol');  // premium paid per contract
            $table->decimal('option_exit_price', 10, 4)->nullable()->after('option_entry_price');
            $table->decimal('option_delta', 6, 4)->nullable()->after('option_exit_price');
            $table->decimal('option_gamma', 6, 4)->nullable()->after('option_delta');
            $table->decimal('option_theta', 6, 4)->nullable()->after('option_gamma');
            $table->decimal('option_iv', 6, 4)->nullable()->after('option_theta');
            $table->decimal('option_strike', 10, 2)->nullable()->after('option_iv');
            $table->date('option_expiry')->nullable()->after('option_strike');
            $table->unsignedSmallInteger('option_contracts')->default(1)->after('option_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('strategy_bots', function (Blueprint $table) {
            $table->dropColumn([
                'trade_type', 'option_delta_target', 'option_delta_tolerance',
                'option_max_dte', 'option_min_dte', 'option_contracts',
                'option_stop_loss_pct', 'option_take_profit_pct',
                'option_order_type', 'option_limit_offset',
            ]);
        });
        Schema::table('strategy_bot_trades', function (Blueprint $table) {
            $table->dropColumn([
                'option_contract_symbol', 'option_entry_price', 'option_exit_price',
                'option_delta', 'option_gamma', 'option_theta', 'option_iv',
                'option_strike', 'option_expiry', 'option_contracts',
            ]);
        });
    }
};
