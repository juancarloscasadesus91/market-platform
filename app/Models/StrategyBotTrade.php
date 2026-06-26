<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyBotTrade extends Model
{
    protected $fillable = [
        'strategy_bot_id', 'symbol', 'direction', 'status',
        'entry_time', 'entry_price', 'quantity', 'entry_value',
        'exit_time', 'exit_price', 'exit_reason',
        'stop_loss', 'take_profit_1', 'take_profit_2', 'take_profit_3',
        'pnl', 'pnl_pct', 'commission',
        'signal_data', 'schwab_order_id', 'schwab_exit_order_id',
        'option_contract_symbol', 'option_entry_price', 'option_exit_price',
        'option_delta', 'option_gamma', 'option_theta', 'option_iv',
        'option_strike', 'option_expiry', 'option_contracts',
    ];

    protected $casts = [
        'entry_time'  => 'datetime',
        'exit_time'   => 'datetime',
        'entry_price' => 'float',
        'exit_price'  => 'float',
        'quantity'    => 'float',
        'entry_value' => 'float',
        'stop_loss'   => 'float',
        'take_profit_1' => 'float',
        'take_profit_2' => 'float',
        'take_profit_3' => 'float',
        'pnl'                  => 'float',
        'pnl_pct'              => 'float',
        'commission'           => 'float',
        'signal_data'          => 'array',
        'option_entry_price'   => 'float',
        'option_exit_price'    => 'float',
        'option_delta'         => 'float',
        'option_gamma'         => 'float',
        'option_theta'         => 'float',
        'option_iv'            => 'float',
        'option_strike'        => 'float',
        'option_contracts'     => 'integer',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(StrategyBot::class, 'strategy_bot_id');
    }
}
