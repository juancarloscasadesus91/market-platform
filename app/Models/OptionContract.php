<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Enums\OptionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol_id',
        'contract_symbol',
        'option_type',
        'strike',
        'expiration_date',
        'bid',
        'ask',
        'last',
        'mark',
        'volume',
        'open_interest',
        'delta',
        'gamma',
        'theta',
        'vega',
        'rho',
        'implied_volatility',
        'in_the_money',
        'intrinsic_value',
        'extrinsic_value',
    ];

    protected $casts = [
        'option_type' => OptionType::class,
        'strike' => 'decimal:4',
        'expiration_date' => 'date',
        'bid' => 'decimal:4',
        'ask' => 'decimal:4',
        'last' => 'decimal:4',
        'mark' => 'decimal:4',
        'volume' => 'integer',
        'open_interest' => 'integer',
        'delta' => 'decimal:6',
        'gamma' => 'decimal:6',
        'theta' => 'decimal:6',
        'vega' => 'decimal:6',
        'rho' => 'decimal:6',
        'implied_volatility' => 'decimal:4',
        'in_the_money' => 'boolean',
        'intrinsic_value' => 'decimal:4',
        'extrinsic_value' => 'decimal:4',
    ];

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }

    public function scopeCalls($query)
    {
        return $query->where('option_type', OptionType::CALL);
    }

    public function scopePuts($query)
    {
        return $query->where('option_type', OptionType::PUT);
    }

    public function scopeByExpiration($query, string $date)
    {
        return $query->where('expiration_date', $date);
    }

    public function scopeInTheMoney($query)
    {
        return $query->where('in_the_money', true);
    }

    public function getVolumeHeatAttribute(): string
    {
        if (!$this->volume) {
            return 'bg-slate-800';
        }

        if ($this->volume > 10000) {
            return 'bg-emerald-500/20';
        }

        if ($this->volume > 5000) {
            return 'bg-blue-500/20';
        }

        if ($this->volume > 1000) {
            return 'bg-yellow-500/20';
        }

        return 'bg-slate-800';
    }
}
