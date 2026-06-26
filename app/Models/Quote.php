<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol_id',
        'last_price',
        'bid',
        'ask',
        'open',
        'high',
        'low',
        'close',
        'change',
        'change_percent',
        'volume',
        'avg_volume',
        'market_cap',
        'pe_ratio',
        'week_52_high',
        'week_52_low',
        'quote_time',
    ];

    protected $casts = [
        'last_price' => 'decimal:4',
        'bid' => 'decimal:4',
        'ask' => 'decimal:4',
        'open' => 'decimal:4',
        'high' => 'decimal:4',
        'low' => 'decimal:4',
        'close' => 'decimal:4',
        'change' => 'decimal:4',
        'change_percent' => 'decimal:4',
        'volume' => 'integer',
        'avg_volume' => 'integer',
        'market_cap' => 'decimal:2',
        'pe_ratio' => 'decimal:2',
        'week_52_high' => 'decimal:4',
        'week_52_low' => 'decimal:4',
        'quote_time' => 'datetime',
    ];

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }

    public function isPositive(): bool
    {
        return $this->change > 0;
    }

    public function isNegative(): bool
    {
        return $this->change < 0;
    }

    public function getDirectionColorAttribute(): string
    {
        if ($this->isPositive()) {
            return 'text-emerald-400';
        }
        
        if ($this->isNegative()) {
            return 'text-rose-400';
        }
        
        return 'text-slate-400';
    }
}
