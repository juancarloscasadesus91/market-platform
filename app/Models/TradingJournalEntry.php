<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingJournalEntry extends Model
{
    protected $fillable = [
        'fecha',
        'capital_inicial_plan',
        'num_trades_plan',
        'profit_percent_plan',
        'profit_diario_plan',
        'formula_plan',
        'capital_final_plan',
        'capital_inicial_real',
        'num_trades_real',
        'profit_percent_real',
        'profit_diario_real',
        'formula_real',
        'capital_final_real',
        'capital_real',
    ];

    protected $casts = [
        'fecha' => 'date',
        'capital_inicial_plan' => 'decimal:2',
        'num_trades_plan' => 'integer',
        'profit_percent_plan' => 'decimal:2',
        'profit_diario_plan' => 'decimal:2',
        'capital_final_plan' => 'decimal:2',
        'capital_inicial_real' => 'decimal:2',
        'num_trades_real' => 'integer',
        'profit_percent_real' => 'decimal:2',
        'profit_diario_real' => 'decimal:2',
        'capital_final_real' => 'decimal:2',
        'capital_real' => 'decimal:2',
    ];

    /**
     * Calculate profit for PLAN
     */
    public function calculatePlanProfit(): void
    {
        $this->profit_diario_plan = $this->capital_inicial_plan * $this->num_trades_plan * ($this->profit_percent_plan / 100);
        $this->capital_final_plan = $this->capital_inicial_plan + $this->profit_diario_plan;
        $this->formula_plan = sprintf(
            '%.2f * %d * (%.2f/100)',
            $this->capital_inicial_plan,
            $this->num_trades_plan,
            $this->profit_percent_plan
        );
    }
    
    /**
     */
    public function calculateRealProfit(): void
    {
        // Calcular profit antes de fees
        $profitBeforeFees = $this->capital_inicial_real * $this->num_trades_real * ($this->profit_percent_real / 100);
        
        // Obtener total de fees de los trades
        $totalFees = $this->trades->sum('fee');
        
        // Profit diario = profit antes de fees - fees
        $this->profit_diario_real = $profitBeforeFees - $totalFees;
        $this->capital_final_real = $this->capital_inicial_real + $this->profit_diario_real;
        
        // Capital real es el capital final
        $this->capital_real = $this->capital_final_real;
        
        $this->formula_real = sprintf(
            '%d × $%.2f × %.2f%% = $%.2f',
            $this->num_trades_real,
            $this->capital_inicial_real,
            $this->profit_percent_real,
            $this->profit_diario_real
        );
    }

    /**
     * Scope to order by date
     */
    public function scopeOrderByDate($query, $direction = 'asc')
    {
        return $query->orderBy('fecha', $direction);
    }

    /**
     * Relationship with trades
     */
    public function trades(): HasMany
    {
        return $this->hasMany(JournalTrade::class, 'trading_journal_entry_id');
    }
    
    /**
     * Calculate real values from trades
     */
    public function calculateRealFromTrades(): void
    {
        $trades = $this->trades;
        
        if ($trades->count() > 0) {
            // Obtener el profit anterior de la base de datos
            $oldEntry = self::find($this->id);
            $oldProfit = $oldEntry ? ($oldEntry->profit_diario_real ?? 0) : 0;
            
            // Número de trades
            $this->num_trades_real = $trades->count();
            
            // Total fees = suma de todos los fees
            $totalFees = $trades->sum('fee');
            
            // Suma de ganancias de todos los trades
            $totalGanancias = $trades->sum('ganancia');
            
            \Log::info('calculateRealFromTrades', [
                'entry_id' => $this->id,
                'num_trades' => $this->num_trades_real,
                'total_ganancias' => $totalGanancias,
                'total_fees' => $totalFees,
                'trades' => $trades->map(fn($t) => [
                    'id' => $t->id,
                    'ganancia' => $t->ganancia,
                    'fee' => $t->fee
                ])
            ]);
            
            // Profit diario = Suma de ganancias - Total Fees
            $this->profit_diario_real = $totalGanancias - $totalFees;
            
            // Recalcular % Profit a partir del profit diario
            // % Profit = (profit_diario + fees) / capital_inicial * 100
            if ($this->capital_inicial_real > 0) {
                $this->profit_percent_real = (($this->profit_diario_real + $totalFees) / $this->capital_inicial_real) * 100;
            } else {
                $this->profit_percent_real = 0;
            }
            
            // Capital final = capital inicial + profit diario
            $this->capital_final_real = $this->capital_inicial_real + $this->profit_diario_real;
            
            // Capital real es el capital final
            $this->capital_real = $this->capital_final_real;
            
            // Generar fórmula: Capital Inicial × % Profit = Profit Diario (ya incluye fees restados)
            $this->formula_real = sprintf(
                '$%.2f × %.2f%% = $%.2f',
                $this->capital_inicial_real,
                $this->profit_percent_real,
                $this->profit_diario_real
            );
            
            // Actualizar portfolio global: restar el profit anterior y sumar el nuevo
            if ($oldProfit != 0) {
                // Primero restar el profit anterior
                PortfolioSetting::updatePortfolio((float)(-$oldProfit));
            }
            // Luego sumar el nuevo profit
            if ($this->profit_diario_real != 0) {
                PortfolioSetting::updatePortfolio((float)$this->profit_diario_real);
            }
        }
    }
}
