<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioSetting extends Model
{
    protected $fillable = [
        'initial_value',
        'current_value',
    ];

    protected $casts = [
        'initial_value' => 'decimal:2',
        'current_value' => 'decimal:2',
    ];
    
    public static function getPortfolio()
    {
        return self::first() ?? self::create([
            'initial_value' => 280.00,
            'current_value' => 280.00,
        ]);
    }
    
    public static function updatePortfolio(float $profitOrLoss): void
    {
        $portfolio = self::getPortfolio();
        $portfolio->current_value += $profitOrLoss;
        $portfolio->save();
    }
}
