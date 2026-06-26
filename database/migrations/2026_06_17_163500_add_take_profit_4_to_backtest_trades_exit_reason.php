<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE backtest_trades MODIFY COLUMN exit_reason
            ENUM('stop_loss','take_profit_1','take_profit_2','take_profit_3','take_profit_4',
                 'end_of_session','time_exit','invalidation') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE backtest_trades MODIFY COLUMN exit_reason
            ENUM('stop_loss','take_profit_1','take_profit_2','take_profit_3',
                 'end_of_session','time_exit','invalidation') NULL");
    }
};
