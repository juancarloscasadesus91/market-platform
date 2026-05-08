<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalTrade extends Model
{
    protected $fillable = [
        'trading_journal_entry_id',
        'symbol',
        'strike_price',
        'capital_usado',
        'profit_percent',
        'ganancia',
        'fee',
    ];

    protected $casts = [
        'strike_price' => 'decimal:2',
        'capital_usado' => 'decimal:2',
        'profit_percent' => 'decimal:2',
        'ganancia' => 'decimal:2',
        'fee' => 'decimal:2',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(TradingJournalEntry::class, 'trading_journal_entry_id');
    }
}
