<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TradingJournalEntry;
use App\Models\JournalTrade;
use Carbon\Carbon;

class CustomTradingJournalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Lógica:
     * - Capital inicial: $280
     * - 2 trades por día
     * - 5% de ganancia por trade hasta que el profit diario llegue a $1000
     * - Después de $1000/día, mantener $1000 de ganancia y decrecer el %
     * - Solo días de semana (lunes a viernes)
     * - Desde 5 de mayo 2026 hasta 31 de diciembre 2026
     */
    public function run(): void
    {
        // Limpiar entradas existentes
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        JournalTrade::truncate();
        TradingJournalEntry::truncate();
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $startDate = Carbon::parse('2026-05-04'); // Lunes 4 de mayo (corregido)
        $endDate = Carbon::parse('2026-12-31');
        $capital = 280.00; // Capital inicial
        $numTrades = 2; // 2 trades por día
        $profitPercent = 5.00; // 5% inicial
        $maxDailyProfit = 1000.00; // Máximo profit diario
        $currentDate = $startDate->copy();
        $entriesCreated = 0;

        $this->command->info('Generando entradas del Trading Journal...');
        $this->command->info('Capital inicial: $280.00');
        $this->command->info('Profit por trade: 5%');
        $this->command->info('Trades por día: 2');
        $this->command->info('');
        
        while ($currentDate->lte($endDate)) {
            // Solo días de semana (lunes a viernes)
            if ($currentDate->isWeekday()) {
                // Calcular profit diario
                $profitDiario = $capital * $numTrades * ($profitPercent / 100);
                
                // Si el profit diario supera $1000, ajustar
                if ($profitDiario > $maxDailyProfit) {
                    $profitDiario = $maxDailyProfit;
                    // Recalcular el porcentaje para mantener $1000 de ganancia
                    $profitPercent = ($maxDailyProfit / ($capital * $numTrades)) * 100;
                }

                // Crear entrada con PLAN y REAL
                $entry = new TradingJournalEntry([
                    'fecha' => $currentDate->format('Y-m-d'),
                    // PLAN (lo que se planeó)
                    'capital_inicial_plan' => $capital,
                    'num_trades_plan' => $numTrades,
                    'profit_percent_plan' => $profitPercent,
                    // REAL (lo que realmente pasó - inicialmente igual al plan)
                    'capital_inicial_real' => $capital,
                    'num_trades_real' => $numTrades,
                    'profit_percent_real' => $profitPercent,
                ]);

                // Calcular PLAN
                $entry->calculatePlanProfit();
                
                // Ajustar PLAN si llegamos al tope
                if ($profitDiario >= $maxDailyProfit) {
                    $entry->profit_diario_plan = $maxDailyProfit;
                    $entry->capital_final_plan = $capital + $maxDailyProfit;
                    $entry->formula_plan = sprintf(
                        '%.2f * %d * (%.4f/100) = $1000 (capped)',
                        $capital,
                        $numTrades,
                        $profitPercent
                    );
                }
                
                // Calcular REAL (inicialmente igual al plan)
                $entry->calculateRealProfit();
                
                // Ajustar REAL si llegamos al tope
                if ($profitDiario >= $maxDailyProfit) {
                    $entry->profit_diario_real = $maxDailyProfit;
                    $entry->capital_final_real = $capital + $maxDailyProfit;
                    $entry->formula_real = sprintf(
                        '%.2f * %d * (%.4f/100) = $1000 (capped)',
                        $capital,
                        $numTrades,
                        $profitPercent
                    );
                }
                
                // Capital real se calcula automáticamente en calculateRealProfit()
                // Ya está asignado en el método
                
                $entry->save();
                
                // Crear trades de ejemplo para este día
                $symbols = ['SPY', 'QQQ', 'AAPL', 'TSLA', 'NVDA', 'AMD', 'MSFT'];
                for ($i = 0; $i < $numTrades; $i++) {
                    JournalTrade::create([
                        'trading_journal_entry_id' => $entry->id,
                        'symbol' => $symbols[array_rand($symbols)],
                        'strike_price' => rand(100, 500) + (rand(0, 99) / 100),
                    ]);
                }

                // Mostrar progreso cada 20 días
                if ($entriesCreated % 20 == 0) {
                    $this->command->info(sprintf(
                        'Día %d (%s): Capital $%s | Profit $%s (%.4f%%) | Final $%s',
                        $entriesCreated + 1,
                        $currentDate->format('Y-m-d'),
                        number_format($capital, 2),
                        number_format($entry->profit_diario, 2),
                        $profitPercent,
                        number_format($entry->capital_final, 2)
                    ));
                }

                // El capital del siguiente día es el capital final REAL de hoy
                $capital = $entry->capital_final_real;
                $entriesCreated++;
            }

            $currentDate->addDay();
        }

        $this->command->info('');
        $this->command->info('✅ Trading journal seeded successfully!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('📊 RESUMEN:');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('Período: ' . $startDate->format('Y-m-d') . ' a ' . $endDate->format('Y-m-d'));
        $this->command->info('Total de entradas: ' . $entriesCreated);
        $this->command->info('Capital inicial: $280.00');
        
        $lastEntry = TradingJournalEntry::orderBy('fecha', 'desc')->first();
        if ($lastEntry) {
            $this->command->info('Capital final: $' . number_format($lastEntry->capital_final_real, 2));
            $totalProfit = $lastEntry->capital_final_real - 280;
            $this->command->info('Ganancia total: $' . number_format($totalProfit, 2));
            $roi = (($lastEntry->capital_final_real - 280) / 280) * 100;
            $this->command->info('ROI: ' . number_format($roi, 2) . '%');
        }
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
