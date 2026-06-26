<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlpacaStrategyLabTrade extends Model
{
    protected $fillable = [
        'alpaca_strategy_lab_session_id', 'symbol', 'direction', 'side', 'status',
        'entry_order_id', 'exit_order_id', 'entry_time', 'entry_price',
        'quantity', 'notional', 'stop_loss', 'take_profit',
        'exit_time', 'exit_price', 'exit_reason', 'pnl', 'pnl_pct',
        'signal_data', 'entry_order_payload', 'exit_order_payload',
        'error_message', 'last_sync_at',
    ];

    protected $casts = [
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
        'entry_price' => 'float',
        'quantity' => 'float',
        'notional' => 'float',
        'stop_loss' => 'float',
        'take_profit' => 'float',
        'exit_price' => 'float',
        'pnl' => 'float',
        'pnl_pct' => 'float',
        'signal_data' => 'array',
        'entry_order_payload' => 'array',
        'exit_order_payload' => 'array',
        'last_sync_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AlpacaStrategyLabSession::class, 'alpaca_strategy_lab_session_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AlpacaStrategyLabLog::class)->latest();
    }
}
