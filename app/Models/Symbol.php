<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Symbol extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticker',
        'name',
        'exchange',
        'sector',
        'industry',
        'market_cap',
        'is_active',
    ];

    protected $casts = [
        'market_cap' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function quote(): HasOne
    {
        return $this->hasOne(Quote::class)->latestOfMany();
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function optionContracts(): HasMany
    {
        return $this->hasMany(OptionContract::class);
    }

    public function watchlist(): HasOne
    {
        return $this->hasOne(Watchlist::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('ticker', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%");
        });
    }
}
