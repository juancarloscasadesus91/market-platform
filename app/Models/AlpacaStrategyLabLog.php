<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlpacaStrategyLabLog extends Model
{
    protected $fillable = [
        'alpaca_strategy_lab_session_id', 'alpaca_strategy_lab_trade_id',
        'level', 'event', 'message', 'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AlpacaStrategyLabSession::class, 'alpaca_strategy_lab_session_id');
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(AlpacaStrategyLabTrade::class, 'alpaca_strategy_lab_trade_id');
    }
}
